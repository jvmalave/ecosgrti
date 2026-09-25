import { ComponentFixture, TestBed } from '@angular/core/testing';
import { GlobalStatusModalComponent } from './global-status-modal.component';

describe('GlobalStatusModalComponent', () => {
  let component: GlobalStatusModalComponent;
  let fixture: ComponentFixture<GlobalStatusModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [GlobalStatusModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(GlobalStatusModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
