import { ComponentFixture, TestBed } from '@angular/core/testing';
import { AuRolesComponent } from './au-roles.component';

describe('AuRolesComponent', () => {
  let component: AuRolesComponent;
  let fixture: ComponentFixture<AuRolesComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AuRolesComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AuRolesComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
