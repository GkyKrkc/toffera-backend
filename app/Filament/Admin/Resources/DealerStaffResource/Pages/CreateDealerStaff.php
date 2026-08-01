<?php

namespace App\Filament\Admin\Resources\DealerStaffResource\Pages;

use App\Filament\Admin\Resources\DealerStaffResource;
use App\Models\DealerStaff;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDealerStaff extends CreateRecord
{
    protected static string $resource = DealerStaffResource::class;

    /**
     * Formdaki 'phone'/'name' alanları DealerStaff modelinin sütunu DEĞİL —
     * burada telefon numarasına göre mevcut kullanıcı bulunur ya da yeni
     * (şifresiz, sadece OTP ile girebilen) bir kullanıcı oluşturulur,
     * ardından gerçek DealerStaff kaydı o kullanıcıya bağlanır.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $user = User::where('phone', $data['phone'])->first();

            if ($user && ($user->hasRole('dealer') || $user->hasRole('dealer_staff'))) {
                throw ValidationException::withMessages([
                    'phone' => 'Bu telefon numarası zaten bir bayilik hesabına (bayi veya personel) ait.',
                ]);
            }

            if (!$user) {
                $user = User::create([
                    'name'              => $data['name'],
                    'phone'             => $data['phone'],
                    'status'            => 'active',
                    'phone_verified_at' => now(),
                ]);
            }

            return DealerStaff::create([
                'user_id'          => $user->id,
                'region_dealer_id' => $data['region_dealer_id'],
                'department'       => $data['department'],
                'is_active'        => $data['is_active'] ?? true,
            ]);
        });
    }
}
