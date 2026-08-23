import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PapRolesComponent } from './pap-roles.component';

describe('PapRolesComponent', () => {
  let component: PapRolesComponent;
  let fixture: ComponentFixture<PapRolesComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PapRolesComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PapRolesComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
