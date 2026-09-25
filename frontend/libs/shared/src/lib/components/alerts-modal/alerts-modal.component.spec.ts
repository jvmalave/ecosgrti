import { ComponentFixture, TestBed } from '@angular/core/testing';
import { AlertsModalComponent } from './alerts-modal.component';

describe('AlertsModalComponent', () => {
  let component: AlertsModalComponent;
  let fixture: ComponentFixture<AlertsModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AlertsModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AlertsModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
