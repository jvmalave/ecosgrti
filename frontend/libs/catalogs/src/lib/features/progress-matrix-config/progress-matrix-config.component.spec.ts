// frontend/libs/catalogs/src/lib/features/progress-matrix-config/progress-matrix-config.component.spec.ts

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { of, throwError } from 'rxjs';

import { ProgressMatrixConfigComponent } from './progress-matrix-config.component';
import { ProgressMatrixService } from '../../data-access/services/progress-matrix.service';
// Provide a local NotificationService abstraction for tests to avoid importing external module
abstract class NotificationService {
  abstract showSuccess(title: string, message: string): void;
  abstract showError(title: string, message: string): void;
  abstract showWarning(title: string, message: string): void;
}
import { ProgressMatrix } from '../../data-access/models/progress-matrix.model';

describe('ProgressMatrixConfigComponent', () => {
  let component: ProgressMatrixConfigComponent;
  let fixture: ComponentFixture<ProgressMatrixConfigComponent>;
  let progressMatrixServiceMock: {
    getActiveMatrix: ReturnType<typeof vi.fn>;
    publishMatrix: ReturnType<typeof vi.fn>;
  };
  let notificationServiceMock: Pick<NotificationService, 'showSuccess' | 'showError' | 'showWarning'>;

  const mockMatrix: ProgressMatrix = {
    matrix_id: 'uuid-mock-1234',
    version_number: 1,
    management_type: 'ENTREGABLES',
    milestones: [
      { id: 'm-1', name: 'Hito 1', weight: 50.00 },
      { id: 'm-2', name: 'Hito 2', weight: 50.00 }
    ]
  };

  beforeEach(async () => {
    progressMatrixServiceMock = {
      getActiveMatrix: vi.fn().mockReturnValue(of(mockMatrix)),
      publishMatrix: vi.fn().mockReturnValue(of({ message: 'Versión V2 publicada', matrix_id: 'uuid-new' }))
    };

    notificationServiceMock = {
      showSuccess: vi.fn(),
      showError: vi.fn(),
      showWarning: vi.fn()
    };

    await TestBed.configureTestingModule({
      imports: [ProgressMatrixConfigComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: ProgressMatrixService, useValue: progressMatrixServiceMock },
        { provide: NotificationService, useValue: notificationServiceMock },
        { provide: 'GLOBAL_API_URL', useValue: 'http://localhost:8000/api' }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(ProgressMatrixConfigComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create the component and load active matrix on init', () => {
    expect(component).toBeTruthy();
    expect(progressMatrixServiceMock.getActiveMatrix).toHaveBeenCalledWith('ENTREGABLES');
    expect(component.milestonesFormArray.length).toBe(2);
    expect(component.totalSumSignal()).toBe(100.00);
  });

  it('should invalidate form and disable publish when sum is not 100.00%', () => {
    const firstMilestone = component.milestonesFormArray.at(0);
    firstMilestone.get('weight')?.setValue(40.00);
    
    fixture.detectChanges();

    expect(component.matrixForm.invalid).toBe(true);
    expect(component.totalSumSignal()).toBe(90.00);
    expect(component.differenceSignal()).toBe(10.00);
  });

  it('should validate exact hundred and allow publishing when sum is exactly 100.00%', () => {
    const firstMilestone = component.milestonesFormArray.at(0);
    firstMilestone.get('weight')?.setValue(60.00);
    const secondMilestone = component.milestonesFormArray.at(1);
    secondMilestone.get('weight')?.setValue(40.00);

    fixture.detectChanges();

    expect(component.matrixForm.valid).toBe(true);
    expect(component.totalSumSignal()).toBe(100.00);

    component.publishNewVersion();

    expect(progressMatrixServiceMock.publishMatrix).toHaveBeenCalled();
    expect(notificationServiceMock.showSuccess).toHaveBeenCalledWith('Versión Publicada', 'Versión V2 publicada');
  });

  it('should handle error when publication fails with 422', () => {
    progressMatrixServiceMock.publishMatrix.mockReturnValue(
      throwError(() => ({ error: { message: 'La suma debe ser 100.00%' } }))
    );

    component.publishNewVersion();

    expect(notificationServiceMock.showError).toHaveBeenCalledWith('Publicación Rechazada', 'La suma debe ser 100.00%');
  });
});