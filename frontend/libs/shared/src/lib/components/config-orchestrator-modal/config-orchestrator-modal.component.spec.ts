import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ConfigOrchestratorModalComponent } from './config-orchestrator-modal.component';

describe('ConfigOrchestratorModalComponent', () => {
  let component: ConfigOrchestratorModalComponent;
  let fixture: ComponentFixture<ConfigOrchestratorModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ConfigOrchestratorModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(ConfigOrchestratorModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
