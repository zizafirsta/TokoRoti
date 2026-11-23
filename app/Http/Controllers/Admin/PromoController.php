<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PromoController extends Controller
{
    public function index()
    {
        $promos = Promo::latest('id')->get();

        return view('admin.promos.index', compact('promos'));
    }

    public function create()
    {
        return view('admin.promos.create');
    }

    // RQ-025 & RQ-026: buat promo valid, tolak data tidak valid / kode duplikat
    public function store(Request $request)
    {
        $data = $this->validated($request);

        Promo::create($data);

        return redirect()->route('admin.promos.index')->with('success', 'Promo berhasil dibuat!');
    }

    public function edit(Promo $promo)
    {
        return view('admin.promos.edit', compact('promo'));
    }

    // RQ-027: ubah data promo
    public function update(Request $request, Promo $promo)
    {
        $data = $this->validated($request, $promo);

        $promo->update($data);

        return redirect()->route('admin.promos.index')->with('success', 'Promo berhasil diperbarui!');
    }

    // RQ-028: aktifkan / nonaktifkan promo
    public function toggle(Promo $promo)
    {
        $promo->update(['is_active' => ! $promo->is_active]);

        $state = $promo->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('admin.promos.index')->with('success', "Promo {$promo->code} berhasil {$state}.");
    }

    /** Validasi bersama untuk store & update. Kode selalu disimpan huruf besar. */
    private function validated(Request $request, ?Promo $promo = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $validator = Validator::make($request->all(), [
            'code'         => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('promos', 'code')->ignore($promo?->id)],
            'type'         => ['required', Rule::in(['percent', 'fixed'])],
            'value'        => ['required', 'numeric', 'gt:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'start_date'   => ['required', 'date'],
            'end_date'     => ['required', 'date', 'after_or_equal:start_date'],
            'quota'        => ['required', 'integer', 'min:1'],
        ], [
            'code.required'            => 'Kode promo wajib diisi.',
            'code.unique'              => 'Kode promo sudah digunakan. Gunakan kode lain.',
            'code.alpha_dash'          => 'Kode promo hanya boleh berisi huruf, angka, strip, dan garis bawah.',
            'type.required'            => 'Tipe promo wajib dipilih.',
            'type.in'                  => 'Tipe promo harus persen atau nominal.',
            'value.required'           => 'Nilai promo wajib diisi.',
            'value.numeric'            => 'Nilai promo harus berupa angka.',
            'value.gt'                 => 'Nilai promo harus lebih besar dari 0.',
            'min_purchase.min'         => 'Minimal belanja tidak boleh negatif.',
            'start_date.required'      => 'Tanggal mulai wajib diisi.',
            'end_date.required'        => 'Tanggal berakhir wajib diisi.',
            'end_date.after_or_equal'  => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
            'quota.required'           => 'Kuota wajib diisi.',
            'quota.integer'            => 'Kuota harus berupa bilangan bulat.',
            'quota.min'                => 'Kuota minimal 1.',
        ]);

        // Promo persen maksimal 100%
        $validator->after(function ($validator) use ($request) {
            if ($request->input('type') === 'percent' && (float) $request->input('value') > 100) {
                $validator->errors()->add('value', 'Nilai promo persen tidak boleh lebih dari 100.');
            }
        });

        $validated = $validator->validate();

        return [
            'code'         => $validated['code'],
            'type'         => $validated['type'],
            'value'        => $validated['value'],
            'min_purchase' => $validated['min_purchase'] ?? 0,
            'start_date'   => $validated['start_date'],
            'end_date'     => $validated['end_date'],
            'quota'        => $validated['quota'],
            'is_active'    => $request->boolean('is_active'),
        ];
    }
}
