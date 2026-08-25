<?php

namespace App\Http\Requests\Attachment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    private const TYPES = ['receipt', 'disbursement', 'bank_fee', 'liability'];
    private const MAX_FILE_KB = 20480;

    public function authorize(): bool
    {
        return $this->user()?->can('attachment.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attachable_type' => ['required', 'in:'.implode(',', self::TYPES)],
            'attachable_id' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:'.self::MAX_FILE_KB, 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.max' => 'Ukuran lampiran maksimal 20 MB.',
            'file.mimes' => 'Format lampiran harus PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS, atau XLSX.',
        ];
    }

    public function attachableType(): string
    {
        return $this->validated('attachable_type');
    }

    public function attachableId(): int
    {
        return (int) $this->validated('attachable_id');
    }
}
