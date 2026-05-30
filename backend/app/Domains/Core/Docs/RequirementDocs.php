<?php

namespace App\Domains\Core\Docs;

use OpenApi\Attributes as OA;
use Illuminate\Http\JsonResponse;
use App\Domains\Core\Http\Requests\StoreRequirementRequest;

interface RequirementDocs
{
    #[OA\Get(
        path: "/api/core/requirements",
        summary: "Listar requerimientos para el Dashboard",
        tags: ["Requerimientos"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Contexto del Dashboard cargado exitosamente.",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Contexto del Dashboard cargado exitosamente."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "string", format: "uuid"),
                                    new OA\Property(property: "rrti", type: "string", example: "REQ-2026-003"),
                                    new OA\Property(property: "status", type: "string", example: "PL"),
                                    new OA\Property(property: "is_locked", type: "boolean", example: false),
                                    new OA\Property(
                                        property: "cspe_consultants",
                                        type: "array",
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: "id", type: "string", format: "uuid"),
                                                new OA\Property(property: "person_id", type: "string", format: "uuid")
                                            ]
                                        )
                                    )
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "No autorizado")
        ]
    )]
    public function index(): JsonResponse;

    #[OA\Post(
        path: "/api/core/requirements",
        summary: "Registrar un nuevo requerimiento (Momento 1)",
        tags: ["Requerimientos"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["rrti", "requirement_type", "creation_date", "description", "management_type", "functional_consultant_id", "cspe_consultants[0]", "needs_spreadsheet", "it_request_doc"],
                    properties: [
                        new OA\Property(property: "rrti", type: "string", example: "REQ-2026-003"),
                        new OA\Property(property: "requirement_type", type: "string", example: "Nuevo Desarrollo"),
                        new OA\Property(property: "creation_date", type: "string", format: "date", example: "2026-05-26"),
                        new OA\Property(property: "description", type: "string", example: "Descripción detallada del requerimiento"),
                        new OA\Property(property: "management_type", type: "string", example: "Soporte Operativo"),
                        new OA\Property(property: "functional_consultant_id", type: "string", format: "uuid", example: "f298ae93-5bd1-40f5-a2a4-74a27ff580b0"),
                        new OA\Property(property: "cspe_consultants[0]", type: "string", format: "uuid", description: "Array de UUIDs de consultores (ej. cspe_consultants[0])"),
                        new OA\Property(property: "needs_spreadsheet", type: "string", format: "binary", description: "Documento de Matriz de Necesidades (PDF/Excel)"),
                        new OA\Property(property: "it_request_doc", type: "string", format: "binary", description: "Documento de Solicitud TI (PDF/Doc)")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Requerimiento creado exitosamente.",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Requerimiento creado exitosamente."),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "string", format: "uuid"),
                                new OA\Property(property: "rrti", type: "string", example: "REQ-2026-003")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Error de validación (Ej. RRTI duplicado o campos faltantes)"),
            new OA\Response(response: 401, description: "No autorizado")
        ]
    )]
    public function store(StoreRequirementRequest $request): JsonResponse;
}