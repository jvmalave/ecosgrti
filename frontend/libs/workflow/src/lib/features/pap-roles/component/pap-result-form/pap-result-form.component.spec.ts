import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PapResultFormComponent } from './pap-result-form.component';

describe('PapResultFormComponent', () => {
  let component: PapResultFormComponent;
  let fixture: ComponentFixture<PapResultFormComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PapResultFormComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(PapResultFormComponent);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
