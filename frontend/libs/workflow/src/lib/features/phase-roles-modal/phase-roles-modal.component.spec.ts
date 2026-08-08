import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PhaseRolesModalComponent } from './phase-roles-modal.component';

describe('PhaseRolesModalComponent', () => {
  let component: PhaseRolesModalComponent;
  let fixture: ComponentFixture<PhaseRolesModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PhaseRolesModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PhaseRolesModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
