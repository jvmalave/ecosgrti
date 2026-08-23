import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PapOrderFormComponent } from './pap-order-form.component';

describe('PapOrderFormComponent', () => {
  let component: PapOrderFormComponent;
  let fixture: ComponentFixture<PapOrderFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PapOrderFormComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PapOrderFormComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
