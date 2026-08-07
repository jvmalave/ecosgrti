import { ComponentFixture, TestBed } from '@angular/core/testing';
import { LifecycleOrchestratorModalComponent } from './lifecycle-orchestrator-modal.component';

describe('LifecycleOrchestratorModalComponent', () => {
  let component: LifecycleOrchestratorModalComponent;
  let fixture: ComponentFixture<LifecycleOrchestratorModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LifecycleOrchestratorModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(LifecycleOrchestratorModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
