import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ReportFilterModalComponent } from './report-filter-modal.component';

describe('ReportFilterModalComponent', () => {
  let component: ReportFilterModalComponent;
  let fixture: ComponentFixture<ReportFilterModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReportFilterModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(ReportFilterModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
