import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RequirementClosureModalComponent } from './requirement-closure-modal.component';

describe('RequirementClosureModalComponent', () => {
  let component: RequirementClosureModalComponent;
  let fixture: ComponentFixture<RequirementClosureModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RequirementClosureModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(RequirementClosureModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
