<?php namespace app\Models;

use Eloquent;
use Auth;
use Cache;
use App\Models\InvoiceDesign;

class InvoiceDesign extends Eloquent
{
    public $timestamps = false;

    public static function getDesigns()
    {
        $organisation = Auth::user()->organisation;
        $designs = Cache::get('invoiceDesigns');

        foreach ($designs as $design) {
            if ($design->id > Auth::user()->maxInvoiceDesignId()) {
                $designs->pull($design->id);
            }
            
            $design->javascript = $design->pdfmake;
            $design->pdfmake = null;

            if ($design->id == CUSTOM_DESIGN) {
                if ($organisation->custom_design) {
                    $design->javascript = $organisation->custom_design;
                } else {
                    $designs->pop();
                }
            }
        }
        
        return $designs;
    }
}