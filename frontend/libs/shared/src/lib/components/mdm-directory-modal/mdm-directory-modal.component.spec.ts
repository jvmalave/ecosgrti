import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MdmDirectoryModalComponent } from './mdm-directory-modal.component';

describe('MdmDirectoryModalComponent', () => {
  let component: MdmDirectoryModalComponent;
  let fixture: ComponentFixture<MdmDirectoryModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MdmDirectoryModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(MdmDirectoryModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
