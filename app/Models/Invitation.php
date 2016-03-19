<?php namespace App\Models;

use Utils;
use Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invitation extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    public function contact()
    {
        return $this->belongsTo('App\Models\Contact')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function organisation()
    {
        return $this->belongsTo('App\Models\Organisation');
    }

    public function getLink($type = 'view')
    {
        if (!$this->organisation) {
            $this->load('organisation');
        }

        $url = SITE_URL;
        $iframe_url = $this->organisation->iframe_url;
        
        if ($this->organisation->isPro()) {
            if ($iframe_url) {
                return "{$iframe_url}/?{$this->invitation_key}";
            } elseif ($this->organisation->subdomain) {
                $url = Utils::replaceSubdomain($url, $this->organisation->subdomain);
            }
        }
        
        return "{$url}/{$type}/{$this->invitation_key}";
    }

    public function getStatus()
    {
        $hasValue = false;
        $parts = [];
        $statuses = $this->message_id ? ['sent', 'opened', 'viewed'] : ['sent', 'viewed'];

        foreach ($statuses as $status) {
            $field = "{$status}_date";
            $date = '';
            if ($this->$field && $this->field != '0000-00-00 00:00:00') {
                $date = Utils::dateToString($this->$field);
                $hasValue = true;
            }
            $parts[] = trans('texts.invitation_status.' . $status) . ': ' . $date;
        }

        return $hasValue ? implode($parts, '<br/>') : false;
    }

    public function getName()
    {
        return $this->invitation_key;
    }

    public function markSent($messageId = null)
    {
        $this->message_id = $messageId;
        $this->email_error = null;
        $this->sent_date = Carbon::now()->toDateTimeString();
        $this->save();
    }

    public function markViewed()
    {
        $invoice = $this->invoice;
        $relation = $invoice->relation;

        $this->viewed_date = Carbon::now()->toDateTimeString();
        $this->save();

        $invoice->markViewed();
        $relation->markLoggedIn();
    }
}
