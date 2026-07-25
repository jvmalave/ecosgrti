import { ComponentFixture, TestBed } from '@angular/core/testing';
import { AtfRolesFormModalComponent } from './atf-roles-form-modal.component';

describe('AtfRolesFormModalComponent', () => {
  let component: AtfRolesFormModalComponent;
  let fixture: ComponentFixture<AtfRolesFormModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AtfRolesFormModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AtfRolesFormModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
