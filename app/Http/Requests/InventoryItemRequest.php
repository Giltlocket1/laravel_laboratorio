<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'category' => ['required', Rule::in(array_keys(config('labkeep.categories')))],
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'unit' => ['required', 'string', 'max:40'], 'location' => ['nullable', 'string', 'max:255'],
            'expiration_date' => ['nullable', 'date_format:Y-m-d'],
            'version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:1'],
            'details' => ['nullable', 'array:chemical_formula,cas_number,composition,concentration,physical_state,color,odor,risks_warnings,responsible,observations,container_content,substance_group,inventory_number,serial_number,no_sep,barcode,capacity,contents,status,marca,modelo,serie,valor,caracteristicas_bien,image_path,pdf_path,external_links'],
        ];
        foreach (['chemical_formula', 'cas_number', 'composition', 'concentration', 'physical_state', 'color', 'odor', 'risks_warnings', 'responsible', 'observations', 'container_content', 'substance_group', 'inventory_number', 'serial_number', 'no_sep', 'barcode', 'capacity', 'contents', 'status', 'marca', 'modelo', 'serie', 'valor', 'caracteristicas_bien'] as $field) {
            $rules['details.'.$field] = ['nullable', 'string', 'max:5000'];
        }
        foreach (['image_path', 'pdf_path'] as $field) {
            $rules['details.'.$field] = ['nullable', 'string', 'max:255', 'regex:~^files/(inventario|oficina|sistemas)/[a-zA-Z0-9_./-]+$~'];
        }
        $rules['details.external_links'] = ['nullable', 'url:http,https', 'max:2000'];

        return $rules;
    }
}
