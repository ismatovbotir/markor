<?php

namespace App\Http\Livewire\Account;

use App\Domain\Printing\Services\LabelImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Company page, owner only: the company logo printed by the "Kompaniya logotipi" template element.
 * PNG / JPG / SVG up to 1 MB; a black-and-white print version is made on upload (see LabelImage).
 */
class CompanyLogo extends Component
{
    use WithFileUploads;

    public $logo = null;

    public function boot(): void
    {
        abort_unless(auth()->user()?->isCompanyOwner() && auth()->user()->company, 403);
    }

    protected function rules(): array
    {
        $ext = implode(',', config('uploads.logo.extensions'));

        return ['logo' => ['required', 'file', 'max:' . config('uploads.logo.max_kb'), 'extensions:' . $ext, 'mimes:' . $ext]];
    }

    public function updatedLogo(): void
    {
        $this->validate();
        try {
            app(LabelImage::class)->storeCompanyLogo(auth()->user()->company, $this->logo);
        } catch (\InvalidArgumentException $e) {
            $this->addError('logo', __($e->getMessage() === 'svg' ? 'company_logo.unsafe_svg' : 'company_logo.unreadable'));
            return;
        } finally {
            $this->reset('logo');
        }
        $this->dispatch('notify', type: 'success', message: __('company_logo.saved'));
    }

    public function remove(): void
    {
        app(LabelImage::class)->removeCompanyLogo(auth()->user()->company);
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render()
    {
        $company = auth()->user()->company;
        $disk = Storage::disk(LabelImage::DISK);

        return view('livewire.account.company-logo', [
            'original' => $company->logo_path ? $disk->url($company->logo_path) : null,
            'print'    => $company->logo_print_path ? $disk->url($company->logo_print_path) : null,
            'printIsSvg' => str_ends_with((string) $company->logo_print_path, '.svg'),
            'upload'   => \App\Support\Uploads::forJs('logo'),
        ]);
    }
}
