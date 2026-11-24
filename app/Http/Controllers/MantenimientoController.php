<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\Mantenimiento;
use App\Services\MantenimientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controlador delgado para el recurso Mantenimiento.
 * Toda la lógica de negocio (transiciones, clima SOAP y fallback 202) vive en MantenimientoService.
 */
class MantenimientoController extends Controller
{
    public function __construct(
        private readonly MantenimientoService $mantenimientoService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $zona = $request->query('zona');
        $mantenimientos = $this->mantenimientoService->listar($zona !== null ? (string) $zona : null);

        return response()->json($mantenimientos, 200);
    }

    public function show(string $id): JsonResponse
    {
        $mantenimiento = $this->mantenimientoService->obtenerPorId($this->parseId($id));

        return response()->json($mantenimiento, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'zona'            => ['required', 'string', Rule::in(Mantenimiento::ZONAS_VALIDAS)],
            'tipo'            => ['required', 'string', Rule::in(Mantenimiento::TIPOS_VALIDOS)],
            'fechaProgramada' => ['required', 'string', 'date_format:Y-m-d'],
            'responsable'     => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $resultado = $this->mantenimientoService->programar($datos);

        return response()->json($resultado['data'], $resultado['status']);
    }

    public function cambiarEstado(Request $request, string $id): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'string', Rule::in(Mantenimiento::ESTADOS_VALIDOS)],
        ]);

        $mantenimiento = $this->mantenimientoService->cambiarEstado($this->parseId($id), $datos['estado']);

        return response()->json($mantenimiento, 200);
    }

    private function parseId(string $id): int
    {
        if (!ctype_digit($id) || (int) $id <= 0) {
            throw new ApiException(400, 'ID_INVALIDO', "El identificador '{$id}' debe ser un entero positivo");
        }

        return (int) $id;
    }
}
