/*
 * Interaksi toko: modal konfirmasi "tambah ke keranjang", keranjang interaktif,
 * toast notifikasi, checkout & halaman pembayaran.
 * Semua komponen didaftarkan lewat event "alpine:init" sehingga urutan import tidak berpengaruh.
 */

const rupiah = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(Number(n) || 0));

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** Panggilan JSON ke server dengan penanganan error yang seragam. */
async function api(url, method = 'GET', body = null) {
    let response;

    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body === null ? null : JSON.stringify(body),
        });
    } catch (e) {
        throw new Error('Tidak dapat terhubung ke server. Periksa koneksi internet Anda.');
    }

    let data = {};
    try {
        data = await response.json();
    } catch (e) {
        // respons bukan JSON
    }

    if (response.status === 401) {
        window.location.href = window.__toko?.loginUrl || '/login';
        throw new Error('Silakan login terlebih dahulu.');
    }

    if (response.status === 419) {
        throw new Error('Sesi Anda sudah habis. Muat ulang halaman lalu coba lagi.');
    }

    if (!response.ok) {
        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        const error = new Error(firstError || data.message || 'Terjadi kesalahan. Silakan coba lagi.');
        error.status = response.status;
        error.data = data;
        throw error;
    }

    return data;
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /* ---------- Store global: keranjang & toast ---------- */
    const initialQty = window.__toko?.cartQty;

    Alpine.store('cart', {
        count: Number(window.__toko?.cartCount || 0),
        qty: Array.isArray(initialQty) || !initialQty ? {} : { ...initialQty },
        bump: false,

        update(count) {
            this.count = Number(count) || 0;
            this.bump = true;
            setTimeout(() => (this.bump = false), 400);
        },
    });

    Alpine.store('toast', {
        items: [],

        show(message, type = 'success', duration = 3500) {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type });
            setTimeout(() => this.dismiss(id), duration);
        },

        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    /* ---------- Modal konfirmasi tambah ke keranjang ---------- */
    Alpine.data('addToCartModal', () => ({
        visible: false,
        stage: 'choose', // 'choose' | 'success'
        loading: false,
        error: '',
        qty: 1,
        product: {},
        result: null,
        rupiah,

        show(product) {
            this.product = product;
            this.qty = 1;
            this.error = '';
            this.result = null;
            this.stage = 'choose';
            this.loading = false;
            this.visible = true;
        },

        close() {
            this.visible = false;
        },

        get inCart() {
            return Alpine.store('cart').qty[this.product.id] || 0;
        },

        get max() {
            return Math.max((this.product.stock || 0) - this.inCart, 0);
        },

        get subtotal() {
            return (Number(this.qty) || 0) * (this.product.price || 0);
        },

        inc() {
            this.error = '';
            if (this.qty < this.max) {
                this.qty++;
            } else {
                this.error = `Stok tersedia hanya ${this.max} pcs.`;
            }
        },

        dec() {
            this.error = '';
            if (this.qty > 1) this.qty--;
        },

        normalize() {
            let n = parseInt(this.qty, 10);
            if (isNaN(n) || n < 1) n = 1;
            if (this.max > 0 && n > this.max) {
                n = this.max;
                this.error = `Stok tersedia hanya ${this.max} pcs.`;
            }
            this.qty = n;
        },

        async confirm() {
            if (this.loading || this.max < 1) return;

            this.normalize();
            this.loading = true;
            this.error = '';

            try {
                const data = await api(this.product.addUrl, 'POST', { quantity: this.qty });
                const cart = Alpine.store('cart');
                cart.qty[this.product.id] = data.in_cart;
                cart.update(data.cart_count);

                this.result = data;
                this.stage = 'success';
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },
    }));

    /* ---------- Halaman keranjang interaktif ---------- */
    Alpine.data('cartPage', (initialItems = []) => ({
        items: initialItems,
        removing: null,
        removeBusy: false,
        timers: {},
        rupiah,

        init() {
            const cart = Alpine.store('cart');
            const map = {};
            this.items.forEach((i) => {
                i.busy = false;
                map[i.id] = i.quantity;
            });
            cart.qty = map;
        },

        get count() {
            return this.items.reduce((sum, i) => sum + i.quantity, 0);
        },

        get total() {
            return this.items.reduce((sum, i) => sum + i.price * i.quantity, 0);
        },

        inc(item) {
            this.setQty(item, item.quantity + 1);
        },

        dec(item) {
            if (item.quantity > 1) this.setQty(item, item.quantity - 1);
        },

        setQty(item, value) {
            let n = parseInt(value, 10);
            if (isNaN(n) || n < 1) n = 1;

            if (n > item.stock) {
                n = item.stock;
                Alpine.store('toast').show(`Stok ${item.name} hanya tersisa ${item.stock} pcs.`, 'warning');
            }

            item.quantity = n;

            // Tunda sebentar agar klik + / - beruntun hanya mengirim satu permintaan
            clearTimeout(this.timers[item.id]);
            this.timers[item.id] = setTimeout(() => this.sync(item), 350);
        },

        async sync(item) {
            if (item.quantity === item.saved) return;

            item.busy = true;

            try {
                const data = await api(item.update_url, 'PATCH', { quantity: item.quantity });
                const cart = Alpine.store('cart');

                item.quantity = data.quantity;
                item.saved = data.quantity;
                item.stock = data.stock;
                cart.qty[item.id] = data.quantity;
                cart.update(data.cart_count);

                if (data.adjusted) {
                    Alpine.store('toast').show(data.message, 'warning');
                }
            } catch (e) {
                item.quantity = item.saved; // batalkan perubahan yang gagal disimpan
                Alpine.store('toast').show(e.message, 'error');
            } finally {
                item.busy = false;
            }
        },

        askRemove(item) {
            this.removing = item;
        },

        async confirmRemove() {
            const item = this.removing;
            if (!item || this.removeBusy) return;

            this.removeBusy = true;

            try {
                const data = await api(item.remove_url, 'DELETE');
                const cart = Alpine.store('cart');

                this.items = this.items.filter((i) => i.id !== item.id);
                delete cart.qty[item.id];
                cart.update(data.cart_count);
                Alpine.store('toast').show(`${item.name} dihapus dari keranjang.`, 'success');
                this.removing = null;
            } catch (e) {
                Alpine.store('toast').show(e.message, 'error');
            } finally {
                this.removeBusy = false;
            }
        },
    }));

    /* ---------- Form checkout ---------- */
    Alpine.data('checkoutForm', (config = {}) => ({
        type: config.type || 'delivery',
        method: config.method || '',
        subtotal: config.subtotal || 0,
        shipping: config.shipping || 0,
        submitting: false,
        rupiah,

        init() {
            // Tombol aktif kembali jika pengguna menekan "Back" dari halaman berikutnya
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) this.submitting = false;
            });
        },

        get shippingFee() {
            return this.type === 'delivery' ? this.shipping : 0;
        },

        get total() {
            return this.subtotal + this.shippingFee;
        },

        submit(event) {
            if (this.submitting) {
                event.preventDefault();
                return;
            }
            this.submitting = true;
        },
    }));

    /* ---------- Halaman pembayaran ---------- */
    Alpine.data('paymentPage', () => ({
        copied: '',
        preview: null,
        fileName: '',
        uploading: false,

        async copy(text, key) {
            try {
                await navigator.clipboard.writeText(text);
            } catch (e) {
                // Cadangan untuk koneksi non-HTTPS
                const area = document.createElement('textarea');
                area.value = text;
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                document.body.removeChild(area);
            }

            this.copied = key;
            setTimeout(() => (this.copied = ''), 1800);
            Alpine.store('toast').show('Berhasil disalin', 'success', 1800);
        },

        pick(event) {
            const file = event.target.files[0];

            if (!file) {
                this.preview = null;
                this.fileName = '';
                return;
            }

            if (file.size > 4 * 1024 * 1024) {
                Alpine.store('toast').show('Ukuran gambar maksimal 4 MB.', 'error');
                event.target.value = '';
                this.preview = null;
                this.fileName = '';
                return;
            }

            this.fileName = file.name;
            this.preview = URL.createObjectURL(file);
        },

        submit(event) {
            if (this.uploading) {
                event.preventDefault();
                return;
            }
            this.uploading = true;
        },
    }));
});
