<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ScenarioEmotionImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmocionesController extends Controller
{
    public function index()
    {
        return view('app');
    }

    public function data(Request $request): JsonResponse
    {
        $meetings = $this->meetingCatalog();
        $localIds = $this->localMeetingIds();
        foreach ($localIds as $id) {
            if (! $meetings->contains(fn ($meeting) => (int) $meeting['id'] === (int) $id)) {
                $meetings->push($this->fallbackMeeting((int) $id));
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
        $details = DB::table('emotions')->where('id_meeting', $meeting);
        $averages = DB::table('emotions_prom')->where('id_meeting', $meeting);
        $webinar = $this->findMeeting($meeting);

        return response()->json([
            'meeting' => $meeting,
            'webinar' => $webinar,
            'nombre_webinar' => $webinar['title'],
            'summary' => $averages->orderBy('id')->get(['id_meeting', 'nivel_atencion_prom', 'emocion_ganadora_prom']),
            'totals' => [
                'records' => (clone $details)->count(),
                'valid' => (clone $details)->where('validez', true)->count(),
                'invalid' => (clone $details)->where('validez', false)->count(),
                'people' => (clone $details)->distinct()->count('id_persona'),
            ],
            'details' => $details->orderBy('fecha')->orderBy('tiempo')->orderBy('id')->paginate(50),
            'archivo' => DB::table('emotions_prom')->where('id_meeting', $meeting)->value('source_file')
                ?? DB::table('emotions')->where('id_meeting', $meeting)->value('source_file'),
        ]);
    }

    /**
     * Public export of every webinar that has emotional data. Each item keeps
     * the external meeting metadata and includes both locally stored formats.
     */
    public function publicIndex(): JsonResponse
    {
        $ids = $this->localMeetingIds();
        $meetings = $this->meetingCatalog()->keyBy('id');
        $averages = DB::table('emotions_prom')->whereIn('id_meeting', $ids)->orderBy('id')->get()->groupBy('id_meeting');
        $details = DB::table('emotions')->whereIn('id_meeting', $ids)->orderBy('fecha')->orderBy('tiempo')->orderBy('id')->get()->groupBy('id_meeting');

        $webinars = $ids->map(fn ($id) => $this->publicEmotionPayload(
            $meetings->get((int) $id, $this->fallbackMeeting((int) $id)),
            $averages->get($id, collect()),
            $details->get($id, collect())
        ))->values();

        return response()->json([
            'total_webinars' => $webinars->count(),
            'webinars' => $webinars,
        ]);
    }

    public function publicDetail(int $meeting): JsonResponse
    {
        return response()->json($this->publicEmotionPayload(
            $this->findMeeting($meeting),
            DB::table('emotions_prom')->where('id_meeting', $meeting)->orderBy('id')->get(),
            DB::table('emotions')->where('id_meeting', $meeting)->orderBy('fecha')->orderBy('tiempo')->orderBy('id')->get()
        ));
    }

    /**
     * Receives a workbook from the authenticated Emotions module. Storage and
     * replacement remain private; public API routes only expose read queries.
     */
    public function uploadInternal(Request $request): JsonResponse
    {
        $validated = $request->validate(['archivo' => ['required', 'file', 'max:51200']]);
        $import = app(ScenarioEmotionImport::class)->read($validated['archivo']);
        abort_unless($import && in_array($import['table'], ['emotions', 'emotions_prom'], true), 422, 'El archivo no contiene un formato emocional válido.');

        $groups = collect($import['rows'])->groupBy(fn (array $row): int => (int) $row['id_meeting']);
        foreach ($groups as $meetingId => $rows) {
            app(ScenarioEmotionImport::class)->persist((int) $meetingId, [
                ...$import,
                'rows' => $rows->values()->all(),
            ]);
        }

        return response()->json([
            'message' => 'Datos emocionales recibidos correctamente.',
            'webinars' => $groups->keys()->map(fn ($id) => (int) $id)->values(),
            'registros' => count($import['rows']),
            'archivo' => $import['source_file'],
        ], 201);
    }

    public function invite(Request $request, int $meeting): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'access_level' => ['required', Rule::in(['manager', 'viewer'])],
        ]);
        $member = User::where('email', $validated['email'])->firstOrFail();
        DB::table('emotion_members')->updateOrInsert(
            ['id_meeting' => $meeting, 'user_id' => $member->id],
            ['access_level' => $validated['access_level'], 'invited_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['message' => 'Acceso al webinar actualizado.', 'member' => [...$member->only(['id', 'name', 'email']), 'access_level' => $validated['access_level']]]);
    }

    public function removeMember(Request $request, int $meeting, User $user): JsonResponse
    {
        $removed = DB::table('emotion_members')->where('id_meeting', $meeting)->where('user_id', $user->id)->delete();
        abort_unless($removed, 404, 'El usuario no tiene acceso a este webinar.');

        return response()->json(['message' => 'Acceso al webinar retirado.']);
    }

    private function meetingCatalog(): Collection
    {
        try {
            $remote = $this->meetingsBL();
        } catch (\Throwable $exception) {
            report($exception);
            $remote = ['meetings' => []];
        }

        return collect($remote['meetings'] ?? $remote['data'] ?? $remote)
            ->map(function ($meeting) {
                $meeting = (array) $meeting;
                $id = $meeting['id'] ?? $meeting['id_meeting'] ?? $meeting['meeting_id'] ?? null;
                if ($id === null) {
                    return null;
                }
                $title = $meeting['title']
                    ?? $meeting['name']
                    ?? $meeting['nombre']
                    ?? $meeting['meeting_name']
                    ?? $meeting['webinar_name']
                    ?? 'Webinar '.$id;
                $topic = $meeting['topic']
                    ?? $meeting['tema']
                    ?? $meeting['description']
                    ?? $meeting['descripcion']
                    ?? null;

                return [
                    ...$meeting,
                    'id' => (int) $id,
                    'id_meeting' => (int) $id,
                    'title' => $title,
                    'nombre' => $title,
                    'topic' => $topic,
                    'tema' => $topic,
                ];
            })
            ->filter()
            ->unique('id')
            ->values();
    }

    private function localMeetingIds(): Collection
    {
        return DB::table('emotions_prom')->distinct()->pluck('id_meeting')
            ->merge(DB::table('emotions')->distinct()->pluck('id_meeting'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();
    }

    private function findMeeting(int $meeting): array
    {
        return $this->meetingCatalog()->firstWhere('id', $meeting) ?? $this->fallbackMeeting($meeting);
    }

    private function fallbackMeeting(int $meeting): array
    {
        return [
            'id' => $meeting,
            'id_meeting' => $meeting,
            'title' => 'Webinar '.$meeting,
            'nombre' => 'Webinar '.$meeting,
            'topic' => 'Datos cargados en el módulo de emociones.',
            'tema' => 'Datos cargados en el módulo de emociones.',
        ];
    }

    private function publicEmotionPayload(array $webinar, Collection $averages, Collection $details): array
    {
        $people = $details->pluck('id_persona')->filter()->unique()->count();
        $averageRows = $averages->map(fn ($row) => $this->publicEmotionRow($row))->values();
        $detailRows = $details->map(fn ($row) => $this->publicEmotionRow($row))->values();

        return [
            'webinar' => [
                'titulo' => $webinar['title'],
                'tema' => $webinar['topic'] ?? null,
            ],
            'data_promedio' => $averageRows,
            'data_completa' => $detailRows,
            'totales' => [
                'promedios' => $averageRows->count(),
                'registros' => $detailRows->count(),
                'validos' => $detailRows->where('validez', true)->count(),
                'invalidos' => $detailRows->where('validez', false)->count(),
                'personas' => $people,
            ],
            'archivos_origen' => [
                'promedio' => $averageRows->pluck('source_file')->filter()->unique()->values(),
                'datos_completos' => $detailRows->pluck('source_file')->filter()->unique()->values(),
            ],
        ];
    }

    private function publicEmotionRow(object $row): array
    {
        $data = collect((array) $row)->except([
            'id',
            'id_persona',
            'id_meeting',
            'record_key',
            'created_at',
            'updated_at',
        ])->all();
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            if ($key === 'validez') {
                $data[$key] = (bool) $value;
            } elseif (Str::startsWith($key, ['score_', 'prob_'])) {
                $data[$key] = (float) $value;
            }
        }

        return $data;
    }
}
