<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Species;
use App\Models\Submission;
use App\Services\Catalogue;
use App\Services\ClusterProgress;
use App\Services\Evidence\InvalidEvidence;
use App\Services\SpeciesArt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Species and stats in the catalogue: add, rename, reorder, switch a species
 * or single stats off, upload a render. Every change is audited (OPR-7) and
 * checked on the server by the admin gate of the route group (SEC-2).
 */
class SpeciesController extends Controller
{
    public function index(ClusterProgress $progress): View
    {
        $species = Species::query()->ordered()->with(['milestones' => fn ($q) => $q->without('species')])->get();

        // Verified and pending claims per species, so an admin sees what a switch affects.
        $claims = Submission::query()
            ->join('milestones', 'milestones.id', '=', 'submissions.milestone_id')
            ->whereIn('submissions.status', [SubmissionStatus::Verified->value, SubmissionStatus::Pending->value])
            ->groupBy('milestones.dinosaur', 'submissions.status')
            ->selectRaw('milestones.dinosaur as dinosaur, submissions.status as status, count(*) as total')
            ->toBase()
            ->get()
            ->groupBy('dinosaur')
            ->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($n) => (int) $n));

        return view('admin.species', [
            'species' => $species,
            'stats' => config('genetic.stats'),
            'claims' => $claims,
            'summary' => $progress->summary(),
            'activeSpecies' => $species->where('is_active', true)->count(),
        ]);
    }

    public function store(Request $request, Catalogue $catalogue, SpeciesArt $art): RedirectResponse
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validateWithBag('add', [
            'code' => ['required', 'string', 'regex:/^[A-Z][A-Z0-9]{1,15}$/', Rule::unique('species', 'code'), Rule::unique('milestones', 'dinosaur')],
            'name' => ['required', 'string', 'max:64'],
            'image' => ['nullable', 'file'],
            'flip' => ['boolean'],
        ], [
            'code.regex' => __('admin.species.errors.code_format'),
        ]);

        $image = $this->storeImage($request, $data['code'], $art);

        if ($image instanceof RedirectResponse) {
            return $image;
        }

        $species = $this->discardOnFailure($art, $image, fn () => $catalogue->create($request->user(), $data['code'], trim($data['name']), $image));

        return redirect()->route('admin.species')->with('status', __('admin.species.created', ['name' => $species->name]));
    }

    public function update(Request $request, Species $species, Catalogue $catalogue, SpeciesArt $art): RedirectResponse
    {
        $bag = "species_{$species->code}";

        $data = $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:64'],
            'is_active' => ['boolean'],
            'stats' => ['array'],
            'stats.*' => ['string', Rule::in(array_keys(config('genetic.stats')))],
            'image' => ['nullable', 'file'],
            'flip' => ['boolean'],
            'remove_image' => ['boolean'],
        ]);

        $active = $request->boolean('is_active');
        $stats = array_values(array_unique($data['stats'] ?? []));

        if ($active && $stats === []) {
            return back()->withErrors(['stats' => __('admin.species.errors.no_stats')], $bag)->withInput();
        }

        $image = $this->storeImage($request, $species->code, $art, $bag);

        if ($image instanceof RedirectResponse) {
            return $image;
        }

        $this->discardOnFailure($art, $image, fn () => $catalogue->update(
            $request->user(), $species, trim($data['name']), $active, $stats, $image, $request->boolean('remove_image'),
        ));

        return redirect()->route('admin.species')->with('status', __('admin.species.saved', ['name' => trim($data['name'])]));
    }

    public function move(Request $request, Species $species, Catalogue $catalogue): RedirectResponse
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        $catalogue->move($request->user(), $species, $data['direction']);

        return redirect()->route('admin.species')->with('status', __('admin.species.moved'));
    }

    /**
     * Checks and stores an uploaded render before the database changes.
     *
     * @return string|RedirectResponse|null the storage key, a redirect back with the error, or null without an upload
     */
    private function storeImage(Request $request, string $code, SpeciesArt $art, string $bag = 'add'): string|RedirectResponse|null
    {
        $file = $request->file('image');

        if ($file === null) {
            return null;
        }

        try {
            return $art->store($file, $code, $request->boolean('flip'));
        } catch (InvalidEvidence $e) {
            return back()->withErrors(['image' => $e->translated()], $bag)->withInput();
        }
    }

    /**
     * Runs the database change; a stored file that ended up unused is removed.
     *
     * @template T
     *
     * @param  callable(): T  $change
     * @return T
     */
    private function discardOnFailure(SpeciesArt $art, ?string $image, callable $change): mixed
    {
        try {
            return $change();
        } catch (Throwable $e) {
            if ($image !== null) {
                $art->discard($image);
            }

            throw $e;
        }
    }
}
