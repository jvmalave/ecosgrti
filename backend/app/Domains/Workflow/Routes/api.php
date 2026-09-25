<?php

use App\Domains\Workflow\Http\Controllers\ATFAgreementController;
use App\Domains\Workflow\Http\Controllers\ATFClosureController;
use App\Domains\Workflow\Http\Controllers\AuRoleController;
use App\Domains\Workflow\Http\Controllers\AuTicketController;
use App\Domains\Workflow\Http\Controllers\CeeDeliverableController;
use App\Domains\Workflow\Http\Controllers\CerRoleController;
use App\Domains\Workflow\Http\Controllers\CoeActivityController;
use App\Domains\Workflow\Http\Controllers\CoeDeliverableController;
use App\Domains\Workflow\Http\Controllers\CorRegisterController;
use App\Domains\Workflow\Http\Controllers\CorRoleController;
use App\Domains\Workflow\Http\Controllers\DeliverableController;
use App\Domains\Workflow\Http\Controllers\DtRegisterController;
use App\Domains\Workflow\Http\Controllers\DtRoleController;
use App\Domains\Workflow\Http\Controllers\PapOrderController;
use App\Domains\Workflow\Http\Controllers\PapRoleController;
use App\Domains\Workflow\Http\Controllers\PiApprovalController;
use App\Domains\Workflow\Http\Controllers\PiRegisterController;
use App\Domains\Workflow\Http\Controllers\PiRoleController;
use App\Domains\Workflow\Http\Controllers\PiTestUserController;
use App\Domains\Workflow\Http\Controllers\ProgressDashboardController;
use App\Domains\Workflow\Http\Controllers\RequirementRoleController;
use App\Domains\Workflow\Http\Controllers\UpdateManagementTypeController;
use Illuminate\Support\Facades\Route;





// Middleware a todo el grupo de workflow para centralizar la seguridad
Route::middleware(['auth:api', 'password.expired'])->prefix('workflow')->group(function () {

  // Middeleware RBAC para los usuarios autorizados a acceder al modulo workflow
  Route::middleware(['role:Admin,Coord,ConsCSPE'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | GESTION DE ACUERDOS ATF 
    |--------------------------------------------------------------------------
    */
    // Registrar Acuerdo ATF
    Route::post('/requirements/{requirementId}/atf-agreements', [ATFAgreementController::class, 'store']);
    // Actualizar Acuerdo ATF
    Route::put('/requirements/{requirementId}/atf-agreements/{agreementId}', [ATFAgreementController::class, 'update']);
    // Listar Acuerdos ATF de un Requerimiento
    Route::get('/requirements/{requirementId}/atf-agreements', [ATFAgreementController::class, 'index']);
    // Eliminar Acuerdo ATF
    Route::delete('/requirements/{requirementId}/atf-agreements/{agreementId}', [ATFAgreementController::class, 'destroy']);


    //===== US26: Gestión de Componente Roles) ======
    // Registrar Rol 
    Route::post('/requirements/{requirementId}/roles', [RequirementRoleController::class, 'store']);
    // Listar Roles de un Requerimiento
    Route::get('/requirements/{requirementId}/components-data', [RequirementRoleController::class, 'index']);
    // Actualizar Rol
    Route::put('/roles/{roleId}', [RequirementRoleController::class, 'update']);
    // Eliminar Rol
    Route::delete('/roles/{roleId}', [RequirementRoleController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | GESTIÓN DE COMPONENTE ENTREGABLES
    |--------------------------------------------------------------------------
    */
    // Registrar Entregable
    Route::post('/requirements/{requirementId}/deliverables', [DeliverableController::class, 'store']);
    // Listar Entregables de un Requerimiento
    Route::get('/requirements/{requirementId}/deliverables', [DeliverableController::class, 'index']);
    // Actualizar Entregable
    Route::put('/deliverables/{deliverableId}', [DeliverableController::class, 'update']);
    // Eliminar Entregable
    Route::delete('/deliverables/{deliverableId}', [DeliverableController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | GESTIÓN DE CIERRE DE FASE ATF  (Hard Gate)
    |--------------------------------------------------------------------------
    */
    // Verificación de Quórum
    Route::get('/requirements/{id}/closure-readiness', [ATFClosureController::class, 'checkReadiness']);
    // Confirmación y Cierre Atómico
    Route::post('/requirements/{id}/close-atf', [ATFClosureController::class, 'closePhase']);

    /*
    |--------------------------------------------------------------------------
    | GESTIÓN FASE DISEÑO TÉCNICO (DT)
    |--------------------------------------------------------------------------
    */

    // -------------------------------------------------------------------------
    // GESTIÓN DE ROLES (DtRoleController)
    // -------------------------------------------------------------------------

    // Acceder a Gestión de Diseño Técnico (DT) y sincronizar roles
    Route::get('/requirements/{id}/dt/roles-init', [DtRoleController::class, 'index']);
    
    // Gestionar Ciclo de Vida del Rol en DT (Cerrar/Activar)
    Route::patch('/dt/roles/{role_id}/status', [DtRoleController::class, 'changeStatus']);
    
    // Cerrar Fase (DT)
    Route::patch('/requirements/{id}/dt/close-phase', [DtRoleController::class, 'closePhase']);

    // -------------------------------------------------------------------------
    // GESTIÓN DE BITÁCORAS / REGISTROS (DtRegisterController)
    // -------------------------------------------------------------------------

    // Consultar Lista de Registros por Rol
    Route::get('/dt/roles/{role_id}/registers', [DtRegisterController::class, 'index']);
    
    // Agregar Registro de Diseño Técnico
    Route::post('/requirements/{id}/dt/roles/{role_id}/registers', [DtRegisterController::class, 'store']);

    // Actualizar Registro de Diseño
    Route::put('/dt/registers/{reg_id}/roles/{role_id}', [DtRegisterController::class, 'update']);
    
    // Eliminar Registro de Diseño (Físico)
    Route::delete('/dt/registers/{reg_id}/roles/{role_id}', [DtRegisterController::class, 'destroy']);
  


    /*
    |--------------------------------------------------------------------------
    | GESTIÓN  FASE CONSTRUCCIÓN - ROLES (COR)
    |--------------------------------------------------------------------------
    */

    // -------------------------------------------------------------------------
    // GESTIÓN DE ROLES (CorRoleController)
    // -------------------------------------------------------------------------
    // Inicializar y listar roles de Construcción por Requerimiento
    Route::get('/requirements/{id}/cor/roles-init', [CorRoleController::class, 'index']);
    
    // Gestionar ciclo de vida del rol en COR (Cerrar/Activar)
    Route::patch('/cor/roles/{role_id}/status', [CorRoleController::class, 'changeStatus']);
    
    // Cierre de fase global de Construcción - Roles
    Route::patch('/requirements/{id}/cor/close-phase', [CorRoleController::class, 'closePhase']);

    // -------------------------------------------------------------------------
    // GESTIÓN DE BITÁCORAS / REGISTROS (CorRegisterController)
    // -------------------------------------------------------------------------
    // Consultar lista de registros por Rol
    Route::get('/cor/roles/{role_id}/registers', [CorRegisterController::class, 'index']);
    
    // Agregar registro a Rol del Requerimiento
    Route::post('/requirements/{id}/cor/roles/{role_id}/registers', [CorRegisterController::class, 'store']);
    
    // Actualizar registro
    Route::put('/cor/registers/{reg_id}/roles/{role_id}', [CorRegisterController::class, 'update']);
    
    // Eliminar registro
    Route::delete('/cor/registers/{reg_id}/roles/{role_id}', [CorRegisterController::class, 'destroy']);


    /*
    |--------------------------------------------------------------------------
    | GESTIÓN FASE CONSTRUCCIÓN - ENTREGABLES (COE)
    |--------------------------------------------------------------------------
    */
  
    Route::prefix('requirements/{id}/coe')->group(function () {
        // Inicializar/Sincronizar entregables desde ATF
        Route::get('/deliverables-init', [CoeDeliverableController::class, 'index']);
        
        // Cierre Global de la Fase COE
        Route::patch('/close-phase', [CoeDeliverableController::class, 'closePhase']);
        
        // Crear una nueva actividad en la bitácora
        Route::post('/deliverables/{deliverable_id}/activities', [CoeActivityController::class, 'store']);
    });

    // 2. Transiciones de Estado Individuales (Entregables)
    // PATCH /workflow/coe/deliverables/{id}/status
    Route::patch('/coe/deliverables/{id}/status', [CoeDeliverableController::class, 'changeStatus']);

    // 3. Bitácora de Actividades (Operaciones sobre el registro específico)
    Route::prefix('coe')->group(function () {
        // Listar actividades de un entregable
        Route::get('/deliverables/{deliverable_id}/activities', [CoeActivityController::class, 'index']);
        
        // Actualizar actividad
        Route::put('/activities/{activity_id}/deliverables/{deliverable_id}', [CoeActivityController::class, 'update']);
        
        // Eliminar actividad (Soft Delete)
        Route::delete('/activities/{activity_id}/deliverables/{deliverable_id}', [CoeActivityController::class, 'destroy']);
    });

  
  /*
  |--------------------------------------------------------------------------
  | GESTIÓN FASE PRUEBAS INTEGRALES (PI))
  |--------------------------------------------------------------------------
  */

  // CU-040: Inicialización y Promoción a Pruebas Integrales (PI)
    Route::get('/requirements/{id}/pi/roles-init', [PiRoleController::class, 'index']);
  
  
    // CU-041: Acceder a Gestión de Pruebas Integrales
    Route::get('/pi/roles/{role_id}/test-users', [PiTestUserController::class, 'index']);
    // Crear Usuario de pruebas 
    Route::post('/pi/roles/{role_id}/test-users', [PiTestUserController::class, 'store']);
    // Borrar Usiario de pruebas
    Route::delete('/pi/test-users/{user_id}', [PiTestUserController::class, 'destroy']);

    // Listar registros en la botacora de Pruebas Integrales
    Route::get('/pi/roles/{role_id}/registers', [PiRegisterController::class, 'index']);
    // Crear una nueva registro en la bitácora
    Route::post('/requirements/{req_id}/pi/roles/{role_id}/registers', [PiRegisterController::class, 'store']);
    // Actualizar registro en la bitacora
    Route::put('/pi/registers/{reg_id}/roles/{role_id}', [PiRegisterController::class, 'update']);
    // Borrar registro de la bitacora
    Route::delete('/pi/registers/{reg_id}/roles/{role_id}', [PiRegisterController::class, 'destroy']);

    // Agregar Aprobación
    Route::post('/requirements/{req_id}/pi/roles/{role_id}/approvals', [PiApprovalController::class, 'store']);
    // Descargar Aprobación
    Route::get('/requirements/{req_id}/pi/roles/{role_id}/approvals/download', [PiApprovalController::class, 'download']); 
    // Cierre individual de Rol PI
    Route::patch('/pi/roles/{role_id}/status', [PiRoleController::class, 'changeStatus']);
    // Cierre Global de la Fase PI
    Route::patch('/requirements/{req_id}/pi/close-phase', [PiRoleController::class, 'closePhase']);
  

    // ==========================================
    // FASE CER (Certificación de Roles) - US33
    // ==========================================

    Route::prefix('cer')->group(function () {
        Route::get('/requirements/{requirementId}/roles-init', [CerRoleController::class, 'index']);

        Route::patch('/requirements/{requirementId}/close', [CerRoleController::class, 'closePhase']);
        
        Route::post('/requirements/{requirementId}/tickets', [CerRoleController::class, 'storeTicket']);
        // Usamos POST (simulando PUT desde Angular) para soportar envío de archivos PDF (Multipart)
        Route::post('/tickets/{ticketId}', [CerRoleController::class, 'updateTicket']); 
        
        Route::post('/tickets/{ticketId}/results', [CerRoleController::class, 'registerResult']);
        Route::post('/tickets/{ticketId}/results-update', [CerRoleController::class, 'updateResult']);

        Route::get('/tickets/{ticketId}/file', [CerRoleController::class, 'downloadFile']);

        Route::get('/tickets/{ticketId}/request-file', [CerRoleController::class, 'downloadRequestFile']);
    });

    // ==========================================
    // FASE CEE (Certificación de Entregables) - US34
    // ==========================================
      
    Route::prefix('cee')->group(function () {
        Route::get('/requirements/{requirementId}/deliverables-init', [CeeDeliverableController::class, 'index']);

        Route::patch('/requirements/{requirementId}/close', [CeeDeliverableController::class, 'closePhase']);
        
        Route::post('/requirements/{requirementId}/tickets', [CeeDeliverableController::class, 'storeTicket']);

        Route::post('/tickets/{ticketId}', [CeeDeliverableController::class, 'updateTicket']); 

        Route::post('/tickets/{ticketId}/results', [CeeDeliverableController::class, 'registerResult']);

        Route::post('/tickets/{ticketId}/results-update', [CeeDeliverableController::class, 'updateResult']);

        Route::get('/tickets/{ticketId}/request-file', [CeeDeliverableController::class, 'downloadRequestFile']);

        Route::get('/tickets/{ticketId}/result-file', [CeeDeliverableController::class, 'downloadResultFile']);
    });
      
      // ==========================================
      // FASE PAP (Pase a Producción) - US35
      // ==========================================

    Route::prefix('pap')->group(function () {
    // Inicializar roles (Promoción desde CER)
        Route::get('/requirements/{requirementId}/roles-init', [PapRoleController::class, 'initRoles']);

        Route::post('/requirements/{requirementId}/orders', [PapOrderController::class, 'store']);

        Route::post('/orders/{orderId}/results', [PapOrderController::class, 'registerResult']);

        Route::get('/orders/{orderId}/download-file', [PapOrderController::class, 'downloadOrderFile']);

        Route::get('/orders/{orderId}/download-result', [PapOrderController::class, 'downloadResultFile']);

        Route::patch('/requirements/{requirementId}/close-phase', [PapRoleController::class, 'closePhase']);

    });

    // ==========================================
    // FASE AU (Asigncion a Usuarios) - US36
    // ==========================================

    Route::prefix('au')->group(function () {
    
        Route::post('/requirements/{requirementId}/tickets', [AuTicketController::class, 'store']);

        Route::get('/requirements/{requirementId}/roles-init', [AuRoleController::class, 'initRoles']);
        
        Route::post('/tickets/{ticketId}/results', [AuTicketController::class, 'registerResult']);

        Route::get('/tickets/download-document', [AuTicketController::class, 'downloadDocument']);

        Route::post('/requirements/{id}/finalize-au', [AuRoleController::class, 'finalizeAu']);
        
    });
  });


  /*
  |--------------------------------------------------------------------------
  | OTRAS RUTAS DE GESTIÓN DE REQUERIMIENTOS
  |--------------------------------------------------------------------------
  */
    // Mostrar Dashboard de Progreso
    Route::get('/requirements/{requirementId}/progress-dashboard', [ProgressDashboardController::class, 'show']);

    Route::middleware(['role:Admin,Coord,ConsCSPE'])->group(function () {
    // Actualizar Tipo de Gestión
      Route::patch('/requirements/{requirementId}/management-type', UpdateManagementTypeController::class);  

  }); 

});