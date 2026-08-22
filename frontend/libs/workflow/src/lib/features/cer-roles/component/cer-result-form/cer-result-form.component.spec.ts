import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CerResultFormComponent } from './cer-result-form.component';

describe('CerResultFormComponent', () => {
  let component: CerResultFormComponent;
  let fixture: ComponentFixture<CerResultFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CerResultFormComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(CerResultFormComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
