<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserDetail extends Model
{
    /** @use HasFactory<\Database\Factories\UserDetailFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'suffix_id',
        'gender_id',
        'civil_status_id',
        'citizenship_id',
        'department_id',
        'position_id',
        'first_name',
        'middle_name',
        'last_name',
        'birthdate',
        'employee_no',
    ];

    /**
     * Get the user.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user suffix.
     *
     * @return BelongsTo
     */
    public function suffix(): BelongsTo
    {
        return $this->belongsTo(Suffix::class, 'suffix_id');
    }

    /**
     * Get the user gender.
     *
     * @return BelongsTo
     */
    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class, 'gender_id');
    }

    /**
     * Get the user civil_status.
     *
     * @return BelongsTo
     */
    public function civil_status(): BelongsTo
    {
        return $this->belongsTo(CivilStatus::class, 'civil_status_id');
    }

    /**
     * Get the user citizenship.
     *
     * @return BelongsTo
     */
    public function citizenship(): BelongsTo
    {
        return $this->belongsTo(Citizenship::class, 'citizenship_id');
    }

    /**
     * Get the user department.
     *
     * @return BelongsTo
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the user position.
     *
     * @return BelongsTo
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
}
