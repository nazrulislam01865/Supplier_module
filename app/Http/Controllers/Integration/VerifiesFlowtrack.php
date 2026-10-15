<?php

namespace App\Http\Controllers\Integration;

use Illuminate\Http\Request;

trait VerifiesFlowtrack
{
    private function signedData(Request $request): ?array
    {
        $secret = (string) config('flowtrack_bridge.secret');
        if (! config('flowtrack_bridge.enabled') || strlen($secret) < 32) return null;
        $body = $request->getContent();
        $time = $request->header('X-FlowTrack-Timestamp');
        $event = $request->header('X-FlowTrack-Event');
        $signature = $request->header('X-FlowTrack-Signature');
        if (strlen($body) > 1048576 || ! is_string($time) || ! ctype_digit($time) ||
            abs(time() - (int) $time) > 300 || ! is_string($event) ||
            ! preg_match('/^[0-9a-f-]{36}$/i', $event) || ! is_string($signature) ||
            ! preg_match('/^[0-9a-f]{64}$/i', $signature) ||
            ! hash_equals(hash_hmac('sha256', $time."\n".$event."\n".$body, $secret), $signature)) return null;
        $data = json_decode($body, true);
        return is_array($data) && ($data['event_id'] ?? null) === $event ? $data : null;
    }
}
