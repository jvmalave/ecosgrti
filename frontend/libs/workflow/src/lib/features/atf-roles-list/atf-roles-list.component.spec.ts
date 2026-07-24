import { ComponentFixture, TestBed } from '@angular/core/testing';
import { AtfRolesListComponent } from './atf-roles-list.component';

describe('AtfRolesListComponent', () => {
  let component: AtfRolesListComponent;
  let fixture: ComponentFixture<AtfRolesListComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AtfRolesListComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AtfRolesListComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
