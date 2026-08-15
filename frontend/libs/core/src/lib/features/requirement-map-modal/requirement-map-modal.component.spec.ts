import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RequirementMapModalComponent } from './requirement-map-modal.component';

describe('RequirementMapModalComponent', () => {
  let component: RequirementMapModalComponent;
  let fixture: ComponentFixture<RequirementMapModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RequirementMapModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(RequirementMapModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
