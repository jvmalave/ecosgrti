<?php

namespace App\Domains\Catalogs\Docs;

use OpenApi\Attributes as OA;
use Illuminate\Http\JsonResponse;

interface CatalogDocs
{
    #[OA\Get(
        path: "/api/catalogs/requirement-types",
        summary: "Obtener lista de Tipos de Requerimiento",
        tags: ["Catálogos"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Operación exitosa",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Tipos de requerimiento recuperados exitosamente"),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "string", format: "uuid"),
                                    new OA\Property(property: "name", type: "string", example: "Nuevo Desarrollo")
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "No autorizado")
        ]
    )]
    public function getRequirementTypes(): JsonResponse;

    #[OA\Get(
        path: "/api/catalogs/management-types",
        summary: "Obtener lista de Tipos de Gestión",
        tags: ["Catálogos"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Operación exitosa",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Tipos de gestión recuperados exitosamente"),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "string", format: "uuid"),
                                    new OA\Property(property: "name", type: "string", example: "Interno")
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "No autorizado")
        ]
    )]
    public function getManagementTypes(): JsonResponse;
}