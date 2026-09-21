import { ComponentFixture, TestBed } from '@angular/core/testing';
import { TraceabilityModalComponent } from './traceability-modal.component';

describe('TraceabilityModalComponent', () => {
  let component: TraceabilityModalComponent;
  let fixture: ComponentFixture<TraceabilityModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TraceabilityModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(TraceabilityModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
