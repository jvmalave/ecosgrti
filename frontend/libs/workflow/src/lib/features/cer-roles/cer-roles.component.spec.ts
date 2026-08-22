import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CerRolesComponent } from './cer-roles.component';

describe('CerRolesComponent', () => {
  let component: CerRolesComponent;
  let fixture: ComponentFixture<CerRolesComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CerRolesComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(CerRolesComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
