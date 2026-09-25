<?php

namespace App\Domains\Workflow\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Domains\Workflow\Models\PapOrder;
use App\Domains\Workflow\Models\PapRole;
use App\Domains\Core\Models\Requirement;
use Exception;

class PapOrderService
{
    /**
     * Almacena la evidencia y consolida la Orden de Transporte
     */
    public function createOrder(string $requirementId, array $data, $file): PapOrder
    {
        return DB::transaction(function () use ($requirementId, $data, $file) {
            
            // 1. Almacenamiento seguro del archivo PDF
            $path = $file->store('pap_orders', 'public'); // Ajusta el disco según tu infraestructura (S3/local)

            // 2. Creación del registro maestro de la Orden
            $order = PapOrder::create([
                'requirement_id' => $requirementId,
                'order_number' => $data['order_number'],
                'date' => $data['date'],
                'file_path' => $path,
                'status' => 'ORD_IN_PROGRESS'
            ]);

            // 3. Sincronización en cascada de los roles seleccionados (Transición a IN_PROGRESS)
            PapRole::whereIn('id', $data['role_ids'])->update([
                'order_id' => $order->id,
                'status' => 'IN_PROGRESS'
            ]);

            // 4. RN-PAP-18: Disparador Transaccional Global (Primer Pase a Producción)
            $totalOrders = PapOrder::where('requirement_id', $requirementId)->count();
            
            if ($totalOrders === 1) {
                Requirement::where('id', $requirementId)->update(['phase_actual' => 'PAP-I']);
                // Aquí podrías invocar a tu servicio de recálculo de progreso:
                // $this->progressService->recalculate($requirementId);
            }

            // 5. RN-PAP-23: Registro de Auditoría
            // auditService->log('CREATE_PAP_ORDER', $order->id, ['roles' => $data['role_ids']]);

            return $order;
        });
    }

    /**
     * Obtiene la ruta física y el nombre sugerido para el archivo base de la orden.
     */
    public function getOrderFileDetails(string $orderId): array
    {
        $order = PapOrder::findOrFail($orderId);

        if (empty($order->file_path) || !Storage::disk('local')->exists($order->file_path)) {
            throw new Exception('El documento de la orden de transporte no se encuentra disponible en el servidor.');
        }

        return [
            'path' => $order->file_path,
            'filename' => "Orden_Transporte_{$order->order_number}.pdf"
        ];
    }

    /**
     * Obtiene la ruta física y el nombre sugerido para el acta de dictamen.
     */
    public function getResultFileDetails(string $orderId): array
    {
        $order = PapOrder::findOrFail($orderId);

        if (empty($order->result_file) || !Storage::disk('local')->exists($order->result_file)) {
            throw new Exception('El acta de dictamen de despliegue no se encuentra disponible en el servidor.');
        }

        return [
            'path' => $order->result_file,
            'filename' => "Dictamen_Despliegue_{$order->order_number}.pdf"
        ];
    }
}