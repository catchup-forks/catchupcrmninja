<?php namespace app\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Validation\Factory;

class UpdateOrganisationRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required',
            'logo' => 'sometimes|max:'.MAX_LOGO_FILE_SIZE.'|mimes:jpeg,gif,png',
        ];
    }
}
// 