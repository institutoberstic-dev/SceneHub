<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ScenarioEmotionImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class EmocionesController extends Controller
{
    public function index()
    {
        return view('app');
    }

    public function data(Request $request): JsonResponse
    {
        try {
            $remote = $this->meetingsBL();
        } catch (\Throwable $exception) {
            report($exception);
            $remote = ['meetings' => []];
        }
        $meetings = collect($remote['meetings'] ?? $remote['data'] ?? $remote);
        $meetings = $meetings->map(function ($meeting) {
            $meeting = (array) $meeting;
            $id = $meeting['id'] ?? $meeting['id_meeting'] ?? $meeting['meeting_id'] ?? null;

            return $id === null ? null : [
                ...$meeting,
                'id' => (int) $id,
                'id_meeting' => (int) $id,
                'title' => $meeting['title'] ?? $meeting['name'] ?? 'Webinar '.$id,
            ];
        })->filter()->values();
        $localIds = DB::table('emotions_prom')->distinct()->pluck('id_meeting')
            ->merge(DB::table('emotions')->distinct()->pluck('id_meeting'))
            ->unique()
            ->values();
        foreach ($localIds as $id) {
            if (! $meetings->contains(fn ($meeting) => (int) $meeting['id'] === (int) $id)) {
                $meetings->push(['id' => (int) $id, 'title' => 'Webinar '.$id, 'topic' => 'Promedio cargado en el módulo de emociones.']);
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

        return response()->json([
            'meeting' => $meeting,
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
     * Receives an emotional workbook from an external integrator. The webinar
     * identifiers come from the workbook's id_meeting column, so no webinar
     * must be selected in the platform before uploading.
     */
    public function uploadExternal(Request $request): JsonResponse
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
}
