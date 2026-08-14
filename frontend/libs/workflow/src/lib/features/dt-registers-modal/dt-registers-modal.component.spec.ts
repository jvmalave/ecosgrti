import { ComponentFixture, TestBed } from '@angular/core/testing';
import { DtRegistersModalComponent } from './dt-registers-modal.component';

describe('DtRegistersModalComponent', () => {
  let component: DtRegistersModalComponent;
  let fixture: ComponentFixture<DtRegistersModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DtRegistersModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(DtRegistersModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
