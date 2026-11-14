<?php

namespace App\Http\Requests;

use App\Enums\EvidenceKind;
use App\Rules\EvidenceImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxFiles = (int) config('genetic.upload.max_files');

        return [
            'milestone_id' => ['required', 'integer', Rule::exists('milestones', 'id')],
            'images' => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'images.*' => ['bail', 'required', 'file', new EvidenceImage],
            'image_types' => ['nullable', 'array'],
            'image_types.*' => ['nullable', Rule::enum(EvidenceKind::class)],
            'player_note' => ['nullable', 'string', 'max:'.(int) config('genetic.player_note_max')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'milestone_id.required' => __('submissions.form.errors.milestone_required'),
            'milestone_id.exists' => __('submissions.form.errors.milestone_required'),
            'images.required' => __('submissions.upload.none'),
            'images.min' => __('submissions.upload.none'),
            'images.max' => __('submissions.upload.too_many', ['max' => config('genetic.upload.max_files')]),
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $files = array_filter((array) $this->file('images', []), fn ($f) => $f instanceof UploadedFile);
                $total = array_sum(array_map(fn (UploadedFile $f) => (int) $f->getSize(), $files));
                $maxTotal = (int) config('genetic.upload.max_total_mb') * 1024 * 1024;

                if ($total > $maxTotal) {
                    $validator->errors()->add('images', __('submissions.upload.total_too_large', ['max' => config('genetic.upload.max_total_mb')]));
                }
            },
        ];
    }

    /** @return list<EvidenceKind> */
    public function kinds(): array
    {
        $types = (array) $this->input('image_types', []);

        return array_values(array_map(
            fn (int $i) => EvidenceKind::tryFrom((string) ($types[$i] ?? '')) ?? EvidenceKind::Other,
            array_keys(array_values((array) $this->file('images', []))),
        ));
    }
}
