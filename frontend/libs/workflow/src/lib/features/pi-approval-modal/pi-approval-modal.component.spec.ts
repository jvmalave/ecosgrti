import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PiApprovalModalComponent } from './pi-approval-modal.component';

describe('PiApprovalModalComponent', () => {
  let component: PiApprovalModalComponent;
  let fixture: ComponentFixture<PiApprovalModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PiApprovalModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PiApprovalModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
