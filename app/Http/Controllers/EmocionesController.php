<?php

namespace App\Http\Controllers;

use App\Models\Escenario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EmocionesController extends Controller
{
    public function index()
    {
        return view('app');
    }

    private function accessible(Request $request)
    {
        return Escenario::query()->when(! $request->user()->hasRole('admin'), function ($query) use ($request) {
            $query->where(fn ($access) => $access->where('owner_id', $request->user()->id)
                ->orWhereHas('users', fn ($users) => $users->where('users.id', $request->user()->id)));
        });
    }

    public function data(Request $request): JsonResponse
    {
        try {
            $remote = $this->meetingsBL();
        } catch (\Throwable $exception) {
            report($exception);
            $remote = ['meetings' => []];
        }
        $meetings = collect($remote['meetings'] ?? []);
        $localIds = DB::table('emotions_prom')->whereIn('escenario_id', $this->accessible($request)->select('id'))->whereNotNull('data_version_id')->distinct()->pluck('id_meeting');
        foreach ($localIds as $id) {
            if (! $meetings->contains(fn ($meeting) => (int) $meeting['id'] === (int) $id)) {
                $meetings->push(['id' => (int) $id, 'title' => 'Webinar '.$id, 'topic' => 'Promedio cargado en el escenario.']);
            }
        }
        return response()->json(['meetings' => $meetings->values()]);
    }

    public function meetingsBL()
    {
        return Http::timeout(15)->get('https://bersticlive.org/api/meetings')->throw()->json();
    }

    public function detail(Request $request, int $meeting): JsonResponse
    {
        $request->validate(['escenario_id' => ['nullable', 'integer', 'min:1']]);
        $scenarios = $this->accessible($request)->where(function ($query) use ($meeting) {
            $query->whereIn('id', DB::table('emotions')->where('id_meeting', $meeting)->select('escenario_id'))
                ->orWhereIn('id', DB::table('emotions_prom')->where('id_meeting', $meeting)->select('escenario_id'));
        })->latest('id')->get(['id', 'nombre']);
        $escenario = $request->filled('escenario_id')
            ? $scenarios->firstWhere('id', (int) $request->input('escenario_id'))
            : $scenarios->first();
        abort_if($request->filled('escenario_id') && ! $escenario, 404);
        $scenario = $escenario?->id ?? 0;
        $details = DB::table('emotions')->where('escenario_id', $scenario)->where('id_meeting', $meeting);
        $averages = DB::table('emotions_prom')->where('escenario_id', $scenario)->where('id_meeting', $meeting);
        $versions = DB::table('data_versions')->where('escenario_id', $scenario)->where('tipo', 'emociones_promedio')->orderByDesc('revision')->get(['id', 'version']);
        $request->validate(['version' => ['nullable', 'regex:/^\d+\.\d+$/']]);
        $version = $request->filled('version') ? $versions->firstWhere('version', $request->version) : $versions->first();
        abort_if($request->filled('version') && ! $version, 404);
        $summary = DB::table('emotions_prom')->where('data_version_id', $version->id ?? 0)->where('id_meeting', $meeting);
        $averages->whereNull('data_version_id');

        return response()->json([
            'scenario' => $escenario?->only(['id', 'nombre']),
            'scenarios' => $scenarios,
            'meeting' => $meeting,
            'version' => $version->version ?? null,
            'versions' => $versions,
            'summary' => $summary->orderBy('id_meeting')->get(['id_meeting', 'nivel_atencion_prom', 'emocion_ganadora_prom']),
            'totals' => [
                'records' => (clone $details)->count(),
                'valid' => (clone $details)->where('validez', true)->count(),
                'invalid' => (clone $details)->where('validez', false)->count(),
                'people' => (clone $details)->distinct()->count('id_persona'),
            ],
            'details' => $details->orderBy('fecha')->orderBy('tiempo')->orderBy('id')->paginate(50),
            'averages' => $averages->orderBy('id_persona')->paginate(50, ['*'], 'averages_page'),
        ]);
    }
}
