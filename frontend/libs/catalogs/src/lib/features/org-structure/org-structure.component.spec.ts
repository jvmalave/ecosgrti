import { ComponentFixture, TestBed } from '@angular/core/testing';
import { OrgStructureComponent } from './org-structure.component';

describe('OrgStructureComponent', () => {
  let component: OrgStructureComponent;
  let fixture: ComponentFixture<OrgStructureComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [OrgStructureComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(OrgStructureComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
