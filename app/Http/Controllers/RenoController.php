<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Reno;
use App\Services\RenoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Controlador delgado para el recurso Reno.
 * Solo valida formato HTTP, delega toda regla de negocio a RenoService y retorna JSON.
 */
class RenoController extends Controller
{
    public function __construct(
        private readonly RenoService $renoService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $estado = $request->query('estado');
        $renos = $this->renoService->listar($estado !== null ? (string) $estado : null);

        return response()->json($renos, 200);
    }

    public function show(string $id): JsonResponse
    {
        $reno = $this->renoService->obtenerPorId($this->parseId($id));

        return response()->json($reno, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre'       => ['required', 'string', 'min:1', 'max:100'],
            'edad'         => ['required', 'integer', 'between:1,25'],
            'nivelEnergia' => ['required', 'integer', 'between:0,100'],
            'estado'       => ['sometimes', 'string', Rule::in(Reno::ESTADOS_VALIDOS)],
        ]);

        $reno = $this->renoService->crear($datos);

        return response()->json($reno, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $datos = $request->validate([
            'nombre'       => ['required', 'string', 'min:1', 'max:100'],
            'edad'         => ['required', 'integer', 'between:1,25'],
            'nivelEnergia' => ['required', 'integer', 'between:0,100'],
            'estado'       => ['sometimes', 'string', Rule::in(Reno::ESTADOS_VALIDOS)],
        ]);

        $reno = $this->renoService->actualizar($this->parseId($id), $datos);

        return response()->json($reno, 200);
    }

    public function destroy(string $id): Response
    {
        $this->renoService->eliminar($this->parseId($id));

        return response()->noContent();
    }

    public function asignarVuelo(string $id): JsonResponse
    {
        $reno = $this->renoService->asignarAlVuelo($this->parseId($id));

        return response()->json($reno, 200);
    }

    private function parseId(string $id): int
    {
        if (!ctype_digit($id) || (int) $id <= 0) {
            throw new ApiException(400, 'ID_INVALIDO', "El identificador '{$id}' debe ser un entero positivo");
        }

        return (int) $id;
    }
}
