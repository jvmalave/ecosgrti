import { ComponentFixture, TestBed } from '@angular/core/testing';
import { EstimationFormComponent } from './estimation-form.component';

describe('EstimationFormComponent', () => {
  let component: EstimationFormComponent;
  let fixture: ComponentFixture<EstimationFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [EstimationFormComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(EstimationFormComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
