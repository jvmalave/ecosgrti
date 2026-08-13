import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MilestoneConfigComponent } from './milestone-config.component';

describe('MilestoneConfigComponent', () => {
  let component: MilestoneConfigComponent;
  let fixture: ComponentFixture<MilestoneConfigComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MilestoneConfigComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(MilestoneConfigComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
