<?php

namespace App\Domains\Security\Services;

use App\Domains\Security\Models\Person;
use App\Domains\Security\Models\User;
use App\Domains\Security\Models\FunctionalConsultant;
use App\Domains\Security\Models\CspeConsultant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use App\Domains\Audit\Services\AuditService;
use Illuminate\Support\Collection;


class UnifiedPersonService
{

  public function __construct(
    protected AuditService $auditService
  ) {}

  /**
   * Consulta el catálogo de Unidades Solicitantes ordenadas alfabéticamente.
   */
  public function getRequestingUnits(): Collection
  {
    return DB::table('requesting_units')
      ->select('id', 'name')
      ->orderBy('name', 'asc')
      ->get();
  }

  /**
   * Registra y aprovisiona una nueva Identidad Unificada dentro de una transacción atómica.
   */
  public function createUnifiedIdentity(array $data): Person
  {
    return DB::transaction(function () use ($data) {

      // 1. Crear el registro base de la Persona
      $person = Person::create([
        'first_name' => $data['first_name'],
        'last_name'  => $data['last_name'],
        'email'      => $data['email'],
        'phone'      => $data['phone'] ?? null,
      ]);

      // 2. Aprovisionamiento de Acceso al Sistema (Usuarios, Gerentes, CSPE)
      if (!empty($data['has_system_access'])) {
        User::create([
          'id'       => $person->id,
          'name'     => $data['name'],
          'email'    => $data['email'],
          'password' => Hash::make($data['password']),
          'roles'    => $data['roles'],
        ]);
      }

      // 3. Aprovisionamiento de Consultor Funcional
      if (!empty($data['is_functional']) && !empty($data['requesting_unit_id'])) {
        FunctionalConsultant::create([
          'person_id'          => $person->id,
          'requesting_unit_id' => $data['requesting_unit_id'],
        ]);
      }

      // 4. Aprovisionamiento de Consultor CSPE
      if (!empty($data['is_cspe'])) {
        CspeConsultant::create([
          'person_id' => $person->id,
        ]);
      }

      // 5. Registro de Auditoría Forense
      $auditService = app(AuditService::class);
      $auditService->logModelChange(
        'CREATE_UNIFIED_PERSON',
        'Se registró una nueva identidad unificada en el sistema: ' . $person->first_name . ' ' . $person->last_name,
        [
          'record_id' => $person->id, // Asegúrate de pasar el ID de la persona recién creada
          'user_id'   => auth()->id() ?? null,
          'first_name' => $person->first_name,
          'last_name'  => $person->last_name,
          // Puedes agregar más datos al payload si lo consideras necesario
        ]
      );

      // 6. Invalidación de Caché de Catálogos de Identidades
      Cache::forget('identities_list_cache');

      return $person;
    });
  }

  /**
   * Actualiza una Identidad Unificada y sincroniza sus perfiles en cascada.
   */
  public function updateUnifiedIdentity(string $id, array $data): Person
  {
    return DB::transaction(function () use ($id, $data) {

      // 1. Actualizar el registro base de la Persona
      $person = Person::findOrFail($id);
      $person->update([
        'first_name' => $data['first_name'],
        'last_name'  => $data['last_name'],
        'email'      => $data['email'],
        'phone'      => $data['phone'] ?? null,
      ]);

      // 2. Sincronización de Acceso al Sistema
      if (!empty($data['has_system_access'])) {
        $user = User::updateOrCreate(
          ['id' => $person->id], // El ID del usuario es el mismo que el de la persona
          [
            'name'  => $data['name'],
            'email' => $data['email'],
            'roles' => $data['roles'],
          ]
        );

        // Solo actualizamos la contraseña si se proporcionó una nueva en el payload
        if (!empty($data['password'])) {
          $user->password = Hash::make($data['password']);
          $user->password_updated_at = null;
          $user->save();

          // 4. Registro de Auditoría: Reseteo Administrativo
          $admin = auth()->user();
          $this->auditService->store(
            'PASSWORD_RESET_ADMIN',
            "El administrador {$admin->name} forzó el restablecimiento de credenciales para la cuenta de {$user->name}.",
            request(),
            $admin->id // El responsable de la acción es el administrador, no el usuario editado
          );
        }
      } else {
        User::where('id', $person->id)->delete();
      }

      // 3. Sincronización de Consultor Funcional
      if (!empty($data['is_functional']) && !empty($data['requesting_unit_id'])) {
        FunctionalConsultant::updateOrCreate(
          ['person_id' => $person->id],
          ['requesting_unit_id' => $data['requesting_unit_id']]
        );
      } else {
        FunctionalConsultant::where('person_id', $person->id)->delete();
      }

      // 4. Sincronización de Consultor CSPE (Sin campo de especialidad)
      if (!empty($data['is_cspe'])) {
        CspeConsultant::updateOrCreate(
          ['person_id' => $person->id],
          []
        );
      } else {
        CspeConsultant::where('person_id', $person->id)->delete();
      }

      // 5. Registro de Auditoría Forense
      $auditService = app(AuditService::class);
      $auditService->logModelChange(
        'UPDATE_UNIFIED_PERSON',
        'Se actualizó la identidad unificada: ' . $person->first_name . ' ' . $person->last_name,
        [
          'record_id' => $person->id,
          'user_id'   => auth()->id() ?? null,
        ]
      );

      // 6. Invalidación de Caché
      Cache::forget('identities_list_cache');

      return $person;
    });
  }

  /**
   * Obtiene el listado paginado de todas las identidades con sus relaciones (Eager Loading).
   * Ideal para llenar la tabla principal de administración.
   */
  public function getAllUnifiedIdentities(int $perPage = 15)
  {
    return Person::with(['user', 'functionalConsultant', 'cspeConsultant'])
      ->orderBy('created_at', 'desc')
      ->paginate($perPage);
  }

  /**
   * Obtiene el detalle de una identidad específica por su UUID con sus perfiles.
   * Ideal para el modo "Mostrar" o "Editar".
   */
  public function getUnifiedIdentityById(string $id): Person
  {
    return Person::with(['user', 'functionalConsultant', 'cspeConsultant'])
      ->findOrFail($id); // Lanza automáticamente 404 si no existe
  }

  /**
   * Aplica la desactivación lógica (Soft Delete) de la identidad y sus perfiles asociados.
   */
  public function deleteUnifiedIdentity(string $id): void
  {
    DB::transaction(function () use ($id) {

      $person = Person::findOrFail($id);

      // 1. Desactivación Lógica de Perfiles Secundarios (Cascada)
      // Al usar delete() en modelos con SoftDeletes, solo se actualiza deleted_at
      if ($person->user) {
        $person->user->delete();
      }
      if ($person->functionalConsultant) {
        $person->functionalConsultant->delete();
      }
      if ($person->cspeConsultant) {
        $person->cspeConsultant->delete();
      }

      // 2. Desactivación Lógica de la Entidad Núcleo
      $person->delete();

      // 3. Registro de Auditoría Forense
      $auditService = app(AuditService::class);
      $auditService->logModelChange(
        'DELETE_UNIFIED_PERSON',
        'Se desactivó lógicamente la identidad unificada: ' . $person->first_name . ' ' . $person->last_name,
        [
          'record_id' => $person->id,
          'user_id'   => auth()->id() ?? null,
        ]
      );

      // 4. Invalidación de Caché
      Cache::forget('identities_list_cache');
    });
  }
}
