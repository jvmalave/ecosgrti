<?php

namespace App\Domains\Security\Docs;

use OpenApi\Attributes as OA;
use Illuminate\Http\JsonResponse;

interface ConsultantDocs
{
    #[OA\Get(
        path: "/api/consultores/functional-consultants",
        summary: "Listar Consultores Funcionales",
        description: "Obtiene los IDs de persona y nombres completos para el select del autocompletado organizacional.",
        tags: ["Consultores"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Consultores funcionales recuperados",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Consultores funcionales recuperados"),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "persona_id", type: "string", format: "uuid", example: "cd33b69c-7de3-4005-993d-3381942f378d"),
                                    new OA\Property(property: "full_name", type: "string", example: "Carlos Funcional")
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "No autorizado")
        ]
    )]
    public function getFunctionalConsultants(): JsonResponse;

    #[OA\Get(
        path: "/api/consultores/cspe-consultants",
        summary: "Listar Consultores CSPE disponibles",
        description: "Obtiene los IDs y nombres completos para la asignación CSPE en el Momento 1 o 2.",
        tags: ["Consultores"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Consultores CSPE recuperados",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Consultores CSPE recuperados"),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "cspe_id", type: "string", format: "uuid", example: "a1b2c3d4-e5f6-7890-1234-56789abcdef0"),
                                    new OA\Property(property: "full_name", type: "string", example: "Ana CSPE")
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "No autorizado")
        ]
    )]
    public function getCspeConsultants(): JsonResponse;
}