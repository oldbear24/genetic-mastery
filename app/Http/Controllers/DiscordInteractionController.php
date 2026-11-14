<?php

namespace App\Http\Controllers;

use App\Services\Discord\GeneticCommands;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP interactions endpoint (/discord/interactions). The signature is
 * checked by VerifyDiscordSignature before this runs.
 */
class DiscordInteractionController extends Controller
{
    private const PING = 1;

    private const APPLICATION_COMMAND = 2;

    public function __invoke(Request $request, GeneticCommands $commands): JsonResponse
    {
        $type = (int) $request->json('type');

        if ($type === self::PING) {
            return response()->json(['type' => 1]);
        }

        if ($type === self::APPLICATION_COMMAND) {
            return response()->json($commands->respond((array) $request->json('data', [])));
        }

        return response()->json(['message' => 'unsupported interaction type'], 400);
    }
}
