import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ReportOrchestratorModalComponent } from './report-orchestrator-modal.component';

describe('ReportOrchestratorModalComponent', () => {
  let component: ReportOrchestratorModalComponent;
  let fixture: ComponentFixture<ReportOrchestratorModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReportOrchestratorModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(ReportOrchestratorModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
