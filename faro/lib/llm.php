<?php
/**
 * Faro — client LLM minimale (Groq, API OpenAI-compatibile).
 *
 * Riusa la GROQ_API_KEY già presente nel .env condiviso. Endpoint ufficiale
 * Groq di default (free, veloce); sovrascrivibile con FARO_LLM_URL/FARO_LLM_MODEL.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// api.groq.com è bloccato in uscita dalla rete OVH: si passa dal worker Cloudflare
// (GROQ_API_URL), proxy trasparente OpenAI-compatibile già usato da helios.
define('FARO_LLM_URL',   faro_env('FARO_LLM_URL',   faro_env('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions')));
define('FARO_LLM_KEY',   faro_env('GROQ_API_KEY',   ''));
define('FARO_LLM_MODEL', faro_env('GROQ_MODEL',     'meta-llama/llama-4-scout-17b-16e-instruct'));

class FaroLlmError extends RuntimeException {}

/**
 * Chat completion. $messages = [['role'=>'system|user','content'=>'...'], ...].
 * Ritorna il testo della risposta. Lancia FaroLlmError su problema.
 */
function faro_llm_chat(array $messages, array $opts = []): string
{
    if (FARO_LLM_KEY === '') {
        throw new FaroLlmError('GROQ_API_KEY non configurata nel .env');
    }
    $payload = [
        'model'       => $opts['model'] ?? FARO_LLM_MODEL,
        'messages'    => $messages,
        'temperature' => $opts['temperature'] ?? 0.2,
        'max_tokens'  => $opts['max_tokens'] ?? 900,
    ];
    if (!empty($opts['json'])) {
        $payload['response_format'] = ['type' => 'json_object'];
    }

    $ch = curl_init(FARO_LLM_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . FARO_LLM_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        throw new FaroLlmError('connessione LLM fallita: ' . $err);
    }
    $data = json_decode($res, true);
    if ($code >= 400 || !isset($data['choices'][0]['message']['content'])) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $code);
        throw new FaroLlmError('LLM: ' . $msg);
    }
    return (string) $data['choices'][0]['message']['content'];
}

/** Estrae il primo oggetto JSON da un testo (gestisce ```json ... ``` o prosa). */
function faro_json_from(string $text): ?array
{
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?|```$/m', '', $text);
    $start = strpos($text, '{');
    $end   = strrpos($text, '}');
    if ($start === false || $end === false || $end < $start) {
        return null;
    }
    $json = substr($text, $start, $end - $start + 1);
    $out = json_decode($json, true);
    return is_array($out) ? $out : null;
}
