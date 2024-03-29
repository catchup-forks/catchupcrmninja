<?php namespace App\Models;

use HTML;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class Contact extends EntityModel implements AuthenticatableContract, CanResetPasswordContract
{
    use SoftDeletes, Authenticatable, CanResetPassword;
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'send_invoice',
    ];

    public static $fieldFirstName = 'first_name';
    public static $fieldLastName = 'last_name';
    public static $fieldEmail = 'email';
    public static $fieldPhone = 'phone';

    public function organisation()
    {
        return $this->belongsTo('App\Models\Organisation');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function relation()
    {
        return $this->belongsTo('App\Models\Relation')->withTrashed();
    }

    public function getPersonType()
    {
        return PERSON_CONTACT;
    }

    public function getName()
    {
        return $this->getDisplayName();
    }

    public function getDisplayName()
    {
        if ($this->getFullName()) {
            return $this->getFullName();
        } else {
            return $this->email;
        }
    }

    public function getFullName()
    {
        if ($this->first_name || $this->last_name) {
            return $this->first_name.' '.$this->last_name;
        } else {
            return '';
        }
    }
}
