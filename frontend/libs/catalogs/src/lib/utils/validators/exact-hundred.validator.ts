// frontend/libs/catalogs/src/lib/utils/validators/exact-hundred.validator.ts

import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

/**
 * Validador personalizado que verifica si la sumatoria de los pesos de los hitos
 * es exactamente igual a 100.00%.
 * Retorna null si es válido, o un objeto de error si hay discrepancias.
 */
export function exactHundredValidator(): ValidatorFn {
  return (control: AbstractControl): ValidationErrors | null => {
    const milestones = control.get('milestones')?.value;
    
    if (!Array.isArray(milestones)) {
      return { notExactHundred: true };
    }

    // 1. Parseo estricto a Number y sumatoria iterativa
    const sum = milestones.reduce((acc, curr) => {
      const weight = Number(curr.weight) || 0;
      return acc + weight;
    }, 0);

    // 2. Redondeo a 2 decimales para eliminar el error de coma flotante de JS
    const roundedSum = Math.round(sum * 100) / 100;

    // 3. Validación matemática estricta
    return roundedSum === 100 ? null : { notExactHundred: true, actualSum: roundedSum };
  };
}

