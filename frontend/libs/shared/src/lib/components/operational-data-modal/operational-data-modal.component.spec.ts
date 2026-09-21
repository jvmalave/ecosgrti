import { ComponentFixture, TestBed } from '@angular/core/testing';
import { OperationalDataModalComponent } from './operational-data-modal.component';

describe('OperationalDataModalComponent', () => {
  let component: OperationalDataModalComponent;
  let fixture: ComponentFixture<OperationalDataModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [OperationalDataModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(OperationalDataModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
