import { ComponentFixture, TestBed } from '@angular/core/testing';
import { UnifiedPersonListModalComponent } from './unified-person-list-modal.component';

describe('UnifiedPersonListModalComponent', () => {
  let component: UnifiedPersonListModalComponent;
  let fixture: ComponentFixture<UnifiedPersonListModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [UnifiedPersonListModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(UnifiedPersonListModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
