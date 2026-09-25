import { ComponentFixture, TestBed } from '@angular/core/testing';
import { TrackingDocumentModalComponent } from './tracking-document-modal.component';

describe('TrackingDocumentModalComponent', () => {
  let component: TrackingDocumentModalComponent;
  let fixture: ComponentFixture<TrackingDocumentModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TrackingDocumentModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(TrackingDocumentModalComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
