import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RequirementModalComponent } from './requirement-modal.component';

describe('RequirementModalComponent', () => {
  let component: RequirementModalComponent;
  let fixture: ComponentFixture<RequirementModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RequirementModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(RequirementModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
