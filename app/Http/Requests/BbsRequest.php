<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * PUNKTET BBS Base Request
 * 
 * Base-klasse for alle API-requests med BBS-tilpasset feilhåndtering
 */
abstract class BbsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    abstract public function rules(): array;

    /**
     * Custom validation messages (norsk)
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute er påkrevd',
            'string' => ':attribute må være tekst',
            'integer' => ':attribute må være et heltall',
            'numeric' => ':attribute må være et tall',
            'email' => ':attribute må være en gyldig e-postadresse',
            'min' => ':attribute må være minst :min tegn',
            'max' => ':attribute kan ikke være mer enn :max tegn',
            'unique' => ':attribute er allerede i bruk',
            'exists' => 'Valgt :attribute eksisterer ikke',
            'confirmed' => ':attribute bekreftelsen stemmer ikke',
            'array' => ':attribute må være en liste',
            'boolean' => ':attribute må være sant eller usant',
            'date' => ':attribute må være en gyldig dato',
            'in' => 'Valgt :attribute er ikke gyldig',
            'regex' => ':attribute har ugyldig format',
            'between' => ':attribute må være mellom :min og :max',
            'size' => ':attribute må være nøyaktig :size tegn',
            'file' => ':attribute må være en fil',
            'image' => ':attribute må være et bilde',
            'mimes' => ':attribute må være av type: :values',
            'max_filesize' => ':attribute kan ikke være større enn :max KB',
        ];
    }

    /**
     * Custom attribute names (norsk)
     */
    public function attributes(): array
    {
        return [
            'username' => 'brukernavn',
            'handle' => 'handle',
            'email' => 'e-post',
            'password' => 'passord',
            'password_confirmation' => 'passordbekreftelse',
            'subject' => 'emne',
            'body' => 'innhold',
            'message' => 'melding',
            'content' => 'innhold',
            'title' => 'tittel',
            'description' => 'beskrivelse',
            'category_id' => 'kategori',
            'forum_id' => 'forum',
            'file' => 'fil',
            'files' => 'filer',
            'name' => 'navn',
            'location' => 'sted',
            'phone' => 'telefon',
            'comment' => 'kommentar',
            'reason' => 'grunn',
            'text' => 'tekst',
            'quote' => 'sitat',
            'answer' => 'svar',
            'option' => 'valg',
            'poll_id' => 'avstemning',
            'option_id' => 'valgmulighet',
        ];
    }

    /**
     * Handle a failed validation attempt - return JSON for API
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => [
                'code' => 422,
                'message' => 'Valideringsfeil - sjekk input-data',
                'fields' => $errors,
            ],
        ], 422));
    }
}
