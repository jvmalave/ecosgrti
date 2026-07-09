import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ProgressDashboardComponent } from './progress-dashboard.component';

describe('ProgressDashboardComponent', () => {
  let component: ProgressDashboardComponent;
  let fixture: ComponentFixture<ProgressDashboardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ProgressDashboardComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(ProgressDashboardComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
