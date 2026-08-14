import { ComponentFixture, TestBed } from '@angular/core/testing';
import { DtRolesListModalComponent } from './dt-roles-list-modal.component';

describe('DtRolesListModalComponent', () => {
  let component: DtRolesListModalComponent;
  let fixture: ComponentFixture<DtRolesListModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DtRolesListModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(DtRolesListModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
