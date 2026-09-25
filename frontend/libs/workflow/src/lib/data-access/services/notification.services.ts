import { Injectable } from '@angular/core';
import Swal from 'sweetalert2';
// import { INotificationService } from '@ecosgrti/shared/interfaces';

@Injectable({
  providedIn: 'root',
})
export class NotificationService {
  toastSuccess(message: string): void {
    Swal.fire({
      icon: 'success',
      title: message,
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
  }

  public showSuccess(title: string, message: string): void {
    Swal.fire({
      title: title,
      html: message,
      icon: 'success',
      confirmButtonText: 'OK',
      buttonsStyling: false,
      customClass: {
        confirmButton:
          'btn btn-tbl-success rounded-pill px-5 py-2 fw-bold shadow-sm',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
    });
  }

  public showError(title: string, message: string): void {
    Swal.fire({
      title: title,
      html: message,
      icon: 'error',
      confirmButtonText: 'Entendido',
      buttonsStyling: false,
      customClass: {
        confirmButton:
          'btn btn-tbl-close rounded-pill px-5 py-2 fw-bold shadow-sm',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
    });
  }

  public showWarning(title: string, message: string): void {
    Swal.fire({
      title: title,
      html: message,
      icon: 'warning',
      confirmButtonText: 'Entendido',
      buttonsStyling: false,
      customClass: {
        confirmButton:
          'btn btn-tbl-close rounded-pill px-5 py-2 fw-bold shadow-sm',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
    });
  }

  async confirm(
    title: string,
    htmlContent: string,
    confirmButtonText = 'Si, continuar',
  ): Promise<boolean> {
    return Swal.fire({
      title: title,
      html: htmlContent,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: confirmButtonText,
      cancelButtonText: 'Cancelar',
      buttonsStyling: false,
      customClass: {
        // Inyectamos tus clases de Bootstrap y la clase corporativa morada
        confirmButton:
          'btn btn-tbl-success rounded-pill px-4 mx-2 fw-bold shadow-sm',
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
    }).then((result) => {
      return result.isConfirmed;
    });
  }

  async confirmDelete(
    title: string,
    htmlContent: string,
    confirmButtonText = 'Si, Borrar',
  ): Promise<boolean> {
    return Swal.fire({
      title: title,
      html: htmlContent,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: confirmButtonText,
      cancelButtonText: 'Cancelar',
      buttonsStyling: false,
      customClass: {
        // Inyectamos tus clases de Bootstrap y la clase corporativa morada
        confirmButton:
          'btn btn-tbl-delete rounded-pill px-4 mx-2 fw-bold shadow-sm',
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
    }).then((result) => {
      return result.isConfirmed;
    });
  }

  async confirmClosure(
    title: string,
    htmlContent: string,
    confirmButtonText = 'Si, Cerrar Fase',
  ): Promise<boolean> {
    return Swal.fire({
      title: title,
      html: htmlContent,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: confirmButtonText,
      cancelButtonText: 'Cancelar',
      buttonsStyling: false,
      customClass: {
        // Inyectamos tus clases de Bootstrap y la clase corporativa morada
        confirmButton:
          'btn btn-tbl-planning rounded-pill px-4 mx-2 fw-bold shadow-sm',
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
    }).then((result) => {
      return result.isConfirmed;
    });
  }

  async promptText(
    title: string,
    htmlText: string,
    placeholder: string,
  ): Promise<string | null> {
    const result = await Swal.fire({
      title: title,
      html: htmlText,
      icon: 'warning',
      input: 'textarea',
      inputPlaceholder: placeholder,
      inputAttributes: {
        'aria-label': placeholder,
      },
      showCancelButton: true,
      confirmButtonText: 'Cerrar y Bloquear',
      cancelButtonText: 'Cancelar',
      customClass: {
        // Inyectamos tus clases de Bootstrap y la clase corporativa morada
        confirmButton:
          'btn btn-tbl-planning rounded-pill px-4 mx-2 fw-bold shadow-sm',
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
      preConfirm: (text) => {
        if (!text || text.trim().length < 10) {
          Swal.showValidationMessage(
            'Debe ingresar una justificación de al menos 10 caracteres.',
          );
          return false;
        }
        return text.trim();
      },
    });
    return result.isConfirmed ? result.value : null;
  }

  async promptTextInput(
    title: string,
    htmlText: string,
    placeholder: string,
  ): Promise<string | null> {
    const result = await Swal.fire({
      title: title,
      html: htmlText,
      icon: 'info',
      input: 'text',
      inputPlaceholder: placeholder,
      showCancelButton: true,
      confirmButtonText:
        '<i class="fa-solid fa-file-pdf me-2"></i> Generar Documento',
      cancelButtonText: 'Cancelar',
      buttonsStyling: false,
      customClass: {
        // Inyectamos tus clases de Bootstrap y la clase corporativa
        confirmButton:
          'btn btn-tbl-info rounded-pill px-4 mx-2 fw-bold shadow-sm',
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
      inputValidator: (value) => {
        if (!value || value.trim() === '') {
          return 'Debe ingresar un número de requerimiento válido.';
        }
        return null;
      },
    });
    return result.isConfirmed ? result.value : null;
  }

  public showLoading(title: string, message: string): void {
    Swal.fire({
      title: title,
      html: message,
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      },
      customClass: {
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
    });
  }

  /**
   * Despliega un modal multicampo para capturar los filtros de la Bitácora de Auditoría.
   */
  async promptAuditLogFilters(): Promise<{
    start_date: string;
    end_date: string;
    rrti: string;
    action: string;
  } | null> {
    const result = await Swal.fire({
      title: 'Filtros de Auditoría',
      html: `
        <div class="text-start fs-6 px-2 mt-3">
          <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Fecha Inicio (Requerido)</label>
          <input id="swal-start-date" type="date" class="form-control mb-3 shadow-sm" required>

          <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Fecha Fin (Requerido)</label>
          <input id="swal-end-date" type="date" class="form-control mb-3 shadow-sm" required>

          <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Código RRTI (Opcional)</label>
          <input id="swal-rrti" type="text" class="form-control mb-3 shadow-sm" placeholder="Ej: 00041601">

          <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Tipo de Evento (Opcional)</label>
          <select id="swal-action" class="form-select shadow-sm">
            <option value="">Todos los eventos (Historial completo)</option>
            <option value="LOGIN_SUCCESS">Inicios de Sesión Exitosos</option>
            <option value="LOGIN_FAIL">Intentos Fallidos de Sesión</option>
            <option value="LOGOUT">Cierres de Sesión</option>
            <option value="CREATE_REQUIREMENT">Creación de Requerimientos</option>
            <option value="PASSWORD_CHANGE">Cambios de Contraseña</option>
            <option value="CREATE_ATF_AGREEMENT">Acuerdos ATF</option>
          </select>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText:
        '<i class="fa-solid fa-file-pdf me-2"></i> Generar Bitácora',
      cancelButtonText: 'Cancelar',
      buttonsStyling: false,
      customClass: {
        confirmButton:
          'btn btn-tbl-planning rounded-pill px-4 mx-2 fw-bold shadow-sm',
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
      preConfirm: () => {
        const startDate = (
          document.getElementById('swal-start-date') as HTMLInputElement
        ).value;
        const endDate = (
          document.getElementById('swal-end-date') as HTMLInputElement
        ).value;
        const rrti = (document.getElementById('swal-rrti') as HTMLInputElement)
          .value;
        const action = (
          document.getElementById('swal-action') as HTMLSelectElement
        ).value;

        if (!startDate || !endDate) {
          Swal.showValidationMessage(
            'Las fechas de inicio y fin son obligatorias.',
          );
          return false;
        }

        if (new Date(startDate) > new Date(endDate)) {
          Swal.showValidationMessage(
            'La fecha de inicio no puede ser posterior a la fecha de fin.',
          );
          return false;
        }

        return {
          start_date: startDate,
          end_date: endDate,
          rrti: rrti ? rrti.replace('#', '').trim() : '',
          action: action,
        };
      },
    });

    return result.isConfirmed ? result.value : null;
  }

  async promptConsultantManagementFilters(
    consultants: { id: string; name: string }[],
  ): Promise<{
    start_date: string;
    end_date: string;
    rrti: string;
    status_type: string;
    consultant_id: string;
    consultant_name: string;
  } | null> {
    // Construimos las opciones del Select iterando la base de datos
    let consultantOptions = '<option value="">Todos los Consultores</option>';
    consultants.forEach((c) => {
      consultantOptions += `<option value="${c.id}">${c.name}</option>`;
    });

    const result = await Swal.fire({
      title: 'Consolidado de Gestión CSPE',
      html: `
        <div class="text-start fs-6 px-2 mt-3">
          <div class="row">
            <div class="col-6">
                <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Fecha Inicio</label>
                <input id="swal-start-date" type="date" class="form-control mb-3 shadow-sm">
            </div>
            <div class="col-6">
                <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Fecha Fin</label>
                <input id="swal-end-date" type="date" class="form-control mb-3 shadow-sm">
            </div>
          </div>

          <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Consultor Asignado</label>
          <select id="swal-consultant" class="form-select mb-3 shadow-sm">
            ${consultantOptions}
          </select>

          <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Filtro por Código RRTI</label>
          <input id="swal-rrti" type="text" class="form-control mb-3 shadow-sm" placeholder="Ej: 00041601">

          <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Estado Operativo</label>
          <select id="swal-status" class="form-select shadow-sm">
            <option value="">Todos los Estados</option>
            <option value="active">Solo Requerimientos Activos (En Proceso)</option>
            <option value="completed">Solo Requerimientos Completados</option>
          </select>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: '<i class="fa-solid fa-file-pdf me-2"></i> Procesar Reporte',
      cancelButtonText: 'Cancelar',
      customClass: {
        confirmButton: 'btn btn-tbl-planning rounded-pill px-4 mx-2 fw-bold shadow-sm',
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
      preConfirm: () => {
        const startDate = (document.getElementById('swal-start-date') as HTMLInputElement).value;
        const endDate = (document.getElementById('swal-end-date') as HTMLInputElement).value;
        const rrti = (document.getElementById('swal-rrti') as HTMLInputElement).value;
        const statusType = (document.getElementById('swal-status') as HTMLSelectElement).value;
        
        // Capturamos el select del consultor
        const consultantSelect = document.getElementById('swal-consultant') as HTMLSelectElement;
        const consultantId = consultantSelect.value;
        // Obtenemos el texto visible (nombre) para enviarlo al PDF
        const consultantName = consultantId ? consultantSelect.options[consultantSelect.selectedIndex].text : '';

        if ((startDate && !endDate) || (!startDate && endDate)) {
          Swal.showValidationMessage('Debe completar el rango ingresando inicio y fin.');
          return false;
        }

        return { 
            start_date: startDate, end_date: endDate,
            rrti: rrti.trim(), status_type: statusType,
            consultant_id: consultantId, consultant_name: consultantName
        };
      }
    });

    return result.isConfirmed ? result.value : null;
  }


  async promptProductionDeploymentsFilters(): Promise<{ start_date: string, end_date: string, rrti: string } | null> {
    const result = await Swal.fire({
      title: 'Histórico de Pases a Producción',
      html: `
        <div class="text-start fs-6 px-2 mt-3">
          <div class="alert alert-info py-2" style="font-size: 12px;">
            <i class="fas fa-info-circle me-1"></i> Deje los campos en blanco para obtener el histórico completo.
          </div>
          <div class="row mt-3">
            <div class="col-6">
                <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Fecha Inicio</label>
                <input id="swal-start-date" type="date" class="form-control mb-3 shadow-sm">
            </div>
            <div class="col-6">
                <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Fecha Fin</label>
                <input id="swal-end-date" type="date" class="form-control mb-3 shadow-sm">
            </div>
          </div>

          <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Filtro por Código RRTI</label>
          <input id="swal-rrti" type="text" class="form-control mb-3 shadow-sm" placeholder="Ej: 00041601">
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: '<i class="fa-solid fa-file-pdf me-2"></i> Generar Histórico',
      cancelButtonText: 'Cancelar',
      customClass: {
        confirmButton: 'btn btn-tbl-planning rounded-pill px-4 mx-2 fw-bold shadow-sm',
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
        title: 'fs-4 text-dark fw-bold',
      },
      preConfirm: () => {
        const startDate = (document.getElementById('swal-start-date') as HTMLInputElement).value;
        const endDate = (document.getElementById('swal-end-date') as HTMLInputElement).value;
        const rrti = (document.getElementById('swal-rrti') as HTMLInputElement).value;

        if ((startDate && !endDate) || (!startDate && endDate)) {
          Swal.showValidationMessage('Debe especificar ambas fechas para filtrar por rango.');
          return false;
        }

        if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
          Swal.showValidationMessage('La fecha de inicio no puede ser mayor a la fecha de fin.');
          return false;
        }

        return { 
            start_date: startDate, 
            end_date: endDate,
            rrti: rrti.trim()
        };
      }
    });

    return result.isConfirmed ? result.value : null;
  }


  async promptReportDateFilter(reportTitle: string): Promise<{ start_date: string, end_date: string } | null> {
    const result = await Swal.fire({
      title: reportTitle,
      html: `
        <div class="text-start fs-6 px-2 mt-3">
          <div class="alert alert-info py-2" style="font-size: 12px;">
            <i class="fas fa-calendar-alt me-1"></i> Seleccione el rango de fechas para acotar el reporte, o déjelo en blanco para el histórico global.
          </div>
          <div class="row mt-3">
            <div class="col-6">
                <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Fecha Inicio</label>
                <input id="swal-start-date" type="date" class="form-control mb-3 shadow-sm">
            </div>
            <div class="col-6">
                <label class="form-label fw-bold text-secondary" style="font-size: 13px;">Fecha Fin</label>
                <input id="swal-end-date" type="date" class="form-control mb-3 shadow-sm">
            </div>
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: '<i class="fa-solid fa-download me-2"></i> Descargar Reporte',
      cancelButtonText: 'Cancelar',
      customClass: {
        confirmButton: 'btn btn-tbl-planning rounded-pill px-4 mx-2 fw-bold shadow-sm',
        cancelButton: 'btn btn-tbl-close rounded-pill px-4 mx-2 fw-medium',
        popup: 'rounded-4 border-top border-4 border-brand',
      },
      preConfirm: () => {
        const startDate = (document.getElementById('swal-start-date') as HTMLInputElement).value;
        const endDate = (document.getElementById('swal-end-date') as HTMLInputElement).value;

        if ((startDate && !endDate) || (!startDate && endDate)) {
          Swal.showValidationMessage('Debe especificar ambas fechas para filtrar por rango.');
          return false;
        }

        if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
          Swal.showValidationMessage('La fecha de inicio no puede ser mayor a la fecha de fin.');
          return false;
        }

        return { 
            start_date: startDate, 
            end_date: endDate 
        };
      }
    });

    return result.isConfirmed ? result.value : null;
  }

  

  public close(): void {
    Swal.close();
  }
}
