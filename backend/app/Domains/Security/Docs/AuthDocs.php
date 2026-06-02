<?php

namespace App\Domains\Security\Docs;

use App\Domains\Security\Http\Requests\LoginRequest;
use OpenApi\Attributes as OA;
use Illuminate\Http\Request;


interface AuthDocs
{
    #[OA\Post(
        path: "/api/auth/login",
        summary: "Autenticar usuario y obtener Token JWT",
        tags: ["Seguridad"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "admin@ecosgrti.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "secret123")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Login exitoso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "access_token", type: "string"),
                        new OA\Property(property: "token_type", type: "string", example: "bearer"),
                        new OA\Property(property: "expires_in", type: "integer")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Credenciales inválidas")
        ]
    )]
    public function login(LoginRequest $request);

    #[OA\Post(
        path: "/api/auth/logout",
        summary: "Cerrar sesión e invalidar token",
        tags: ["Seguridad"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Logout exitoso"),
            new OA\Response(response: 401, description: "No autorizado")
        ]
    )]
    public function logout(Request $request);
}