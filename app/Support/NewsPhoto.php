<?php

namespace App\Support;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\MediaAsset;
use App\Services\MediaLibrary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "Upload a photo" on the news form, beside "Choose from the media library".
 *
 * The upload is stored by the media library's own MediaLibrary::store, so
 * it is deduplicated, stripped of EXIF and given the same WebP renditions
 * as a photograph uploaded on the media screen, and it lands in the
 * library where the next editor can choose it. What the form adds is the
 * rule the brief sets for it: JPG, PNG or WebP, at most 10 MB, and alt
 * text in all three languages before anything is saved.
 */
final class NewsPhoto
{
    public const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp'];

    public const MAX_KILOBYTES = 10 * 1024;

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $rules = [
            'photo_upload' => ['nullable', 'file', 'mimetypes:'.implode(',', self::ACCEPTED), 'max:'.self::MAX_KILOBYTES],
            'photo_used_for' => ['nullable', 'string', \Illuminate\Validation\Rule::in(ImageSlots::keys())],
            'hero_focal_x' => ['nullable', 'integer', 'min:0', 'max:100', 'required_with:hero_focal_y'],
            'hero_focal_y' => ['nullable', 'integer', 'min:0', 'max:100', 'required_with:hero_focal_x'],
        ];

        foreach (Locales::SUPPORTED as $locale) {
            $rules['photo_alt.'.$locale] = ['nullable', 'required_with:photo_upload', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Store an uploaded photograph and point the field at it, and keep the
     * focal point only when the form sent both percentages for a photograph
     * (the form clears them when the photograph is changed).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function apply(Resource $definition, Model $record, Request $request, array $data): array
    {
        $field = collect($definition->fields)->first(fn (Field $f) => $f->control === 'photo');

        if ($field === null) {
            return $data;
        }

        if ($request->hasFile('photo_upload')) {
            Gate::authorize('create', MediaAsset::class);

            $asset = app(MediaLibrary::class)->store($request->file('photo_upload'), $request->user()->id);

            /*
             * A photograph already in the library comes back as the same
             * asset. Its existing alt text is kept, because other pages
             * may show it; the languages it had none for are filled from
             * this form.
             */
            $existing = $asset->translationValuesFor('alt');
            foreach (Locales::SUPPORTED as $locale) {
                if (trim((string) ($existing[$locale] ?? '')) === '') {
                    $asset->setTranslation('alt', $locale, $data['photo_alt'][$locale] ?? null, $request->user()->id);
                }
            }

            $data[$field->name] = $asset->path;

            // Item 1i: a photograph smaller than its slot is saved, with a
            // warning on the form it returns to.
            $warning = ImageSlots::warning($asset, $request->input('photo_used_for', 'news'));
            if ($warning !== null) {
                session()->flash('m3_media_warning', $warning);
            }
        }

        $x = $field->options['x'];
        $y = $field->options['y'];

        if (($data[$x] ?? null) === null || ($data[$y] ?? null) === null || empty($data[$field->name])) {
            $data[$x] = null;
            $data[$y] = null;
        }

        return $data;
    }
}
