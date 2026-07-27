import { ComponentFixture, TestBed } from '@angular/core/testing';
import { UnifiedPersonModalComponent } from './unified-person-modal.component';

describe('UnifiedPersonModalComponent', () => {
  let component: UnifiedPersonModalComponent;
  let fixture: ComponentFixture<UnifiedPersonModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [UnifiedPersonModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(UnifiedPersonModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
