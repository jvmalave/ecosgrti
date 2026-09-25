import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CspeWorkloadModalComponent } from './cspe-workload-modal.component';

describe('CspeWorkloadModalComponent', () => {
  let component: CspeWorkloadModalComponent;
  let fixture: ComponentFixture<CspeWorkloadModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CspeWorkloadModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(CspeWorkloadModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
