import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PapHistoryModalComponent } from './pap-history-modal.component';

describe('PapHistoryModalComponent', () => {
  let component: PapHistoryModalComponent;
  let fixture: ComponentFixture<PapHistoryModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PapHistoryModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PapHistoryModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
