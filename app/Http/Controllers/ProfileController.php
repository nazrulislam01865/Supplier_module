<?php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'name'=>['required','string','max:255'],
            'legal_name'=>['nullable','string','max:255'],
            'country'=>['nullable','string','max:100'],
            'registration_number'=>['nullable','string','max:100'],
            'description'=>['nullable','string','max:2000'],
            'primary_contact'=>['nullable','string','max:120'],
            'primary_email'=>['nullable','email','max:254'],
            'phone'=>['nullable','string','max:60'],
            'finance_contact'=>['nullable','string','max:120'],
            'finance_email'=>['nullable','email','max:254'],
            'operations_contact'=>['nullable','string','max:120'],
            'registered_address'=>['nullable','string','max:1500'],
            'factory_address'=>['nullable','string','max:1500'],
            'city'=>['nullable','string','max:100'],
            'postal_code'=>['nullable','string','max:40'],
            'beneficiary_name'=>['nullable','string','max:255'],
            'bank_name'=>['nullable','string','max:255'],
            'bank_currency'=>['nullable', Rule::in(['USD','CNY','EUR','GBP'])],
            'swift'=>['nullable','string','max:32'],
            'payment_terms'=>['nullable','string','max:255'],
        ]);
        abort_unless($request->user()->role === 'admin', 403, 'Only supplier administrators may edit the company profile.');
        $company = $request->user()->company;
        $company->update([
            'name'=>$data['name'],
            'profile'=>array_intersect_key($data, array_flip([
                'legal_name','country','registration_number','description','primary_contact','primary_email','phone',
                'finance_contact','finance_email','operations_contact','registered_address','factory_address','city','postal_code'
            ])),
            'encrypted_banking'=>array_intersect_key($data, array_flip(['beneficiary_name','bank_name','bank_currency','swift','payment_terms'])),
        ]);
        return response()->json(['message'=>'Company profile saved.']);
    }
}
