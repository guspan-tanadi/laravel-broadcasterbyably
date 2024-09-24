<?php

namespace Ably\LaravelBroadcaster;

use Ably\Exceptions\AblyException;
use Illuminate\Broadcasting\BroadcastException;

class Utils
{
    // JWT related PHP utility functions
    /**
     * @param  string  $jwt
     * @return array
     */
    public static function parseJwt($jwt)
    {
        $tokenParts = explode('.', $jwt);
        $header = json_decode(base64_decode($tokenParts[0]), true);
        $payload = json_decode(base64_decode($tokenParts[1]), true);

        return ['header' => $header, 'payload' => $payload];
    }

    /**
     * @param  array  $headers
     * @param  array  $payload
     * @return string
     */
    public static function generateJwt($headers, $payload, $key)
    {
        $encodedHeaders = self::base64urlEncode(json_encode($headers));
        $encodedPayload = self::base64urlEncode(json_encode($payload));

        $signature = hash_hmac('SHA256', "$encodedHeaders.$encodedPayload", $key, true);
        $encodedSignature = self::base64urlEncode($signature);

        return "$encodedHeaders.$encodedPayload.$encodedSignature";
    }

    /**
     * @param  string  $jwt
     * @param  mixed  $timeFn
     * @return bool
     */
    public static function isJwtValid($jwt, $timeFn, $key)
    {
        // split the jwt
        $tokenParts = explode('.', $jwt);
        $header = $tokenParts[0];
        $payload = $tokenParts[1];
        $tokenSignature = $tokenParts[2];

        // check the expiration time - note this will cause an error if there is no 'exp' claim in the jwt
        $expiration = json_decode(base64_decode($payload))->exp;
        $isTokenExpired = $expiration <= $timeFn();

        // build a signature based on the header and payload using the secret
        $signature = hash_hmac('SHA256', $header.'.'.$payload, $key, true);
        $isSignatureValid = self::base64urlEncode($signature) === $tokenSignature;

        return $isSignatureValid && ! $isTokenExpired;
    }

    public static function base64urlEncode($str)
    {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }


    const SOCKET_ID_ERROR = "please make sure to send base64 encoded json with "
    ."'connectionKey' and 'clientId' as keys. 'clientId' is null if connection is not identified";

    /**
     * @throws AblyException
     */
    public static function decodeSocketId($socketId) {
        $socketIdObject = null;
        if ($socketId) {
            try {
                $socketIdObject = json_decode(base64_decode($socketId));
            } catch (\Exception $e) {
                throw new AblyException("SocketId decoding failed, ".self::SOCKET_ID_ERROR, 0, $e);
            }
            if (!isset($socketIdObject->connectionKey)) {
                throw new AblyException("ConnectionKey is missing, ".self::SOCKET_ID_ERROR);
            }
            if (!isset($socketIdObject->clientId)) {
                throw new AblyException("ClientId is missing, ".self::SOCKET_ID_ERROR);
            }
        }
        return $socketIdObject;
    }
}
