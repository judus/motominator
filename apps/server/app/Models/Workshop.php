<?php

namespace App\Models;

use Database\Factories\WorkshopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $identity
 * @property string $name
 * @property string|null $address
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $tax_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static \Database\Factories\WorkshopFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
class Workshop extends Model
{
    /** @use HasFactory<WorkshopFactory> */
    use HasFactory;

    protected $guarded = ['id'];
}
