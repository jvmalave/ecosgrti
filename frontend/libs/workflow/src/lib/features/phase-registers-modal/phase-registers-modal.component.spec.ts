import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PhaseRegistersModalComponent } from './phase-registers-modal.component';

describe('PhaseRegistersModalComponent', () => {
  let component: PhaseRegistersModalComponent;
  let fixture: ComponentFixture<PhaseRegistersModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PhaseRegistersModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PhaseRegistersModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
